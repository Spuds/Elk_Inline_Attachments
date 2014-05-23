<?php

/**
 * @name      Inline Attachments (ILA)
 * @license   Mozilla Public License version 1.1 http://www.mozilla.org/MPL/1.1/.
 * @author    Spuds
 * @copyright (c) 2014 Spuds
 *
 * @version   1.0
 *
 * Based on original code by mouser http://www.donationcoder.com
 * Updated/Modified/etc with permission
 *
 */

// Thats just no to you
if (!defined('ELK'))
	die('No access...');

/**
 * Searches a post for all ila tags and trys to replace them with the destinations
 * image, link, etc
 */
class ILA_Parse_BBC
{
	// Set some things up front
	protected $_ila_dont_show_attach_below = array();
	protected $_ila_attachments_context = array();
	protected $_ila_new_msg_preview = array();
	protected $_ila_attachments = array();
	protected $_board = null;
	protected $_start_num = 0;
	protected $_message = '';
	protected $_topic = '';
	protected $_id_msg = null;

	/**
	 * Holds current instance of the class
	 * @var Database_MySQL
	 */
	private static $_ila_parser = null;

	private function __construct(&$message, $id_msg)
	{
		$this->_message = $message;
		$this->_id_msg = $id_msg;
		require_once (SUBSDIR . '/Attachments.subs.php');
	}

	/**
	 * ila_parse_bbc()
	 *
	 * Traffic cop, checks permissions and finds all [attach tags, determins msg number, inits values
	 * and calls needed functions to render ila tags
	 */
	public function ila_parse_bbc()
	{
		global $modSettings, $context, $txt, $attachments;

		// Mod or BBC disabled, can't do anything !
		if (empty($modSettings['ila_enabled']) || empty($modSettings['enableBBC']))
			return $this->_message;;

		// Previewing a modified message, check for $_REQUEST['msg']
		$this->_id_msg = empty($this->_id_msg) ? (isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : -1) : $this->_id_msg;

		// No message id and not previewing a new message ($_REQUEST['ila'] will be set)
		if ($this->_id_msg === -1 && !isset($_REQUEST['ila']))
		{
			// Make sure block quotes are cleaned up, then return
			$this->ila_find_nested();
			return $this->_message;;
		}

		// If there is a message number then get the topic and board that *its* from, we
		// can't trust the $topic global due to portals and other integration
		$this->ila_get_topic();

		// Lets make sure we have the attachments, for this message, to work with so we can get the context array
		if (!isset($attachments[$this->_id_msg]))
			$attachments[$this->_id_msg] = $this->ila_load_attachments($this->_id_msg, $this->_topic, $this->_board);

		// Now get the rest of the details for these attachments
		$this->_ila_attachments_context = loadAttachmentContext($this->_id_msg);

		// Do we have new --- not yet uploaded ---- attachments in either a new or a modified message?
		if (isset($_REQUEST['ila']))
		{
			$this->_start_num = isset($attachments[$this->_id_msg]) ? count($attachments[$this->_id_msg]) : 0;
			$ila_temp = explode(',', $_REQUEST['ila']);

			foreach ($ila_temp as $new_attach)
			{
				// Add at the end of the currenlty uploaded attachment count index
				$this->_start_num++;
				$this->_ila_new_msg_preview[$this->_start_num] = $new_attach;
			}
		}

		// Take care of any attach links that reside in quote blocks, we must render these out first
		$this->ila_find_nested();

		// Find all of the inline attach tags in this message
		// [attachimg=xx] [attach=xx] [attachthumb=xx] [attachurl=xx] [attachmini=xx] or
		// some malformed ones like [attachIMG = "xx"]
		// ila_tags[0] will hold the entire tag [1] will hold the attach type (before the ]) eg img=1
		$ila_tags = array();
		if (preg_match_all('~\[attach\s*?(.*?(?:".+?")?.*?|.*?)\][\r\n]?~i', $this->_message, $ila_tags))
		{
			// Load a simple array of elements.  We use it to keep track of attachment number usage in the message body
			$this->_ila_attachments = !empty($this->_start_num) ? range(1, $this->_start_num) : range(1, count($attachments[$this->_id_msg]));
			$ila_num = 0;

			// If they have no permissions to view attachments then we sub out the tag with the appropriate message
			if (!allowedTo('view_attachments', $this->_board))
			{
				$this->_message = preg_replace_callback('~\[attach\s*?(.*?(?:".+?")?.*?|.*?)\][\r\n]?~i',
				function() use($context) {return $context['user']['is_guest'] ? $txt['ila_forbidden_for_guest'] : $txt['ila_nopermission'];},
				$this->_message);
			}
			else
			{
				// If we have attachments, and ILA tags then go through each ILA tag,
				// one by one, and resolve it back to the correct ELK attachment
				if (!empty($ila_tags) && ((count($this->_ila_attachments_context) > 0) || (isset($_REQUEST['ila']))))
				{
					foreach ($ila_tags[1] as $id => $ila_replace)
					{
						$this->_message = $this->ila_str_replace_once($ila_tags[0][$id], $this->ila_parse_bbc_tag($ila_replace, $this->_ila_attachments_context, $this->_id_msg, $ila_num, $this->_ila_new_msg_preview), $this->_message);
						$ila_num++;
					}
				}
				// We have tags in the message and no attachments, repalce them with an failed message
				elseif (!empty($ila_tags))
				{
					// There are a few reasons why this can, and does, occur
					//
					// - The tags in the message but there is no attachments, perhaps the attachment did not upload correctly
					// - The user put the tag in wrong because they are rock dumb and did not read our fantastic help,
					// just kidding, really the help is not that good.
					// - They don't have premission to view attachments in that board
					foreach ($ila_tags[1] as $id => $ila_replace)
						$this->_message = $this->ila_str_replace_once($ila_tags[0][$id], $txt['ila_invalid'], $this->_message);
				}
			}
		}

		return $this->_message;
	}

	/**
	 * ila_parse_bbc_tag()
	 *
	 * - Breaks up the components of the [attach tag getting id, width, align
	 * - Fixes some common usage errors
	 *
	 * @param string $data
	 * @param mixed[] $attachments
	 * @param int $ila_num
	 */
	private function ila_parse_bbc_tag($data, $attachments, $ila_num)
	{
		$done = array('id' => '', 'type' => '', 'align' => '', 'width' => '');
		$data = trim($data);

		// Find the align tag, save its value and remove it from the data string
		$matches = array();
		if (preg_match('~align\s{0,1}=(?:&quot;)?(right|left|center)(?:&quot;)?~i', $data, $matches))
		{
			$done['align'] = strtolower($matches[1]);
			$data = str_replace($matches[0], '', $data);
		}

		// Find the width tag, save its value and remove it from the data string
		if (preg_match('~width\s{0,1}=(?:&quot;)?(\d+)(?:&quot;)?~i', $data, $matches))
		{
			$done['width'] = strtolower($matches[1]);
			$data = str_replace($matches[0], '', $data);
		}

		// All that should be left is the id and tag, split on = to see what we have
		$temp = array();
		$result = preg_match('~(.*?)=(\d+).*~', $data, $temp);
		if ($result && $temp[1] != '')
		{
			// One of img=1 thumb=1 mini=1 url=1, we hope ;)
			$done['id'] = isset($temp[2]) ? trim($temp[2]) : '';
			$done['type'] = $temp[1];
		}
		else
		{
			// Nothing but a =x, or =x and wrong tags, or even perhaps nothing at all since we support that to!
			$done['id'] = isset($temp[2]) ? trim($temp[2]) : '';
			$done['type'] = 'none';
		}

		// Lets help the kids out by fixing some common erros in usage, I mean did they read the super great help?
		// like attach=#1 -> attach=1
		$done['id'] = str_replace('#', '', $done['id']);

		// like [attach] -> attach=1 by assuming attachments are sequentally placed in the
		// topic and sub in the attachment index increment
		if (is_numeric($done['id']))
			// Remove this attach choice since we have used it
			$this->_ila_attachments = array_diff($this->_ila_attachments, array($done['id']));
		else
		{
			// Take the first un-used attach number and use it
			$done['id'] = array_shift($this->_ila_attachments);

			// Stick it back on the end in case we need to loop around
			array_push($this->_ila_attachments, $done['id']);
		}

		// Replace this tag with the inlined attachment
		$result = $this->ila_showInline($done, $attachments, $this->_id_msg, $ila_num, $this->_ila_new_msg_preview);

		return !empty($result) ? $result : '[attach' . $data . ']';
	}

	/**
	 * ila_find_nested()
	 *
	 * - Does [attach replacements in quotes and nested quotes
	 * - Look for quote blocks with ila attach tags and builds the link.
	 * - Replaces ila attach tags in quotes with a link back to the post with the attachment
	 * - Prevents ILA from firing on those attach tags should we have a quote block with an
	 * attach placed in a message with an attach
	 *
	 * - Is painfully complicated as is, should consider other approaches me thinks
	 */
	private function ila_find_nested()
	{
		global $modSettings, $context, $txt, $scripturl;

		// Should not get to this point but ....
		if (empty($modSettings['enableBBC']))
			return;

		// Regexs to seach the message for quotes, nested quotes and quoted text, and tags
		$regex = array();
		$regex['quotelinks'] = '~<div\b[^>]*class="topslice_quote">(?:.*?)</div>~si';
		$regex['ila'] = '~\[attach\s*?(.*?(?:".+?")?.*?|.*?)\][\r\n]?~i';

		// Break up the quotes on the endtags, this way we will get *all* the needed text
		$quotes = preg_split('~(.*?</blockquote>)~si', $this->_message, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		// The last one is junk, strip it off ...
		array_pop($quotes);

		// Init
		$quote_count = count($quotes);
		$loop = $quote_count;
		$start = 0;

		// Loop through the quote array
		while ($quote_count > 0 && $loop > 0)
		{
			//  Get all the topslice quotes, they contain the links (or not) of the message that was quoted, each link represents a quoteblock
			$blockquote_count = preg_match_all($regex['quotelinks'], $quotes[$start], $links, PREG_SET_ORDER);
			$quote_count = $quote_count - $blockquote_count;

			// $quote_count will control the while, but belt and suspenders here we keep a
			// loop count to stop a run away ....
			$loop += -1;

			// If this has blockquotes, we have work to do, we will have a nesting level of blockquote_count
			if (!empty($blockquote_count))
			{
				// Flip the array, quotes are outside to inside and links are inside to outside,
				// its a nesting thing to mess with your mind.
				$links = array_reverse($links);

				// Scrape off anything ahead of a leading quoteheader ... its regular message text,
				// likely between quoted zones
				$temp = array();
				if ((strpos($quotes[$start], '<div class="quoteheader">') != 0) && (preg_match('~.*(<div class="quoteheader">.*)~si', $quotes[$start], $temp)))
					$quotes[$start] = $temp[1];

				// Set the end of the link/quote array look ahead
				$end = $start + $blockquote_count - 1;
				$which_link = 0;

				// This quote block runs from array elements $start to $end
				for ($i = $start; $i <= $end; $i++)
				{
					// Search the link to get the msg_id
					$msg_id = '';
					$href_temp = array();
					if (preg_match('~<a href="(?:.*)#(.*?)">~i', $links[$which_link][0], $href_temp) == 1)
						$msg_id = $href_temp[1];

					// We either found the quoted msg id above or we did not, yes profound I know ....
					// if none set the link to the first message of the thread.
					if ($msg_id == '')
						$msg_id = isset($context['topic_first_message']) ? $context['topic_first_message'] : '';

					// Build the link, we will replace any quoted ILA tags with this bad boy
					if ($msg_id != '')
					{
						if (!isset($context['current_topic']))
							$this->ila_get_topic();
						else
							$this->_topic = $context['current_topic'];
						$linktoquotedmsg = '<a href="' . $scripturl . '/topic,' . $this->_topic . '.' . $msg_id . '.html#' . $msg_id . '">' . $txt['ila_quote_link'] . '</a>';
					}
					else
						$linktoquotedmsg = $txt['ila_quote_nolink'];

					// The link back is the same for all the ila tags in an individual quoteblock
					// (they all point back to the same message)
					$ila_tags = array();
					if (preg_match_all($regex['ila'], $quotes[$i], $ila_tags))
					{
						// We have found an ila tag, in this quoted message section
						$ila_string = $quotes[$i];

						// replace the ila attach tag with the link back to the message that was quoted
						foreach ($ila_tags[0] as $id => $ila_replace)
							$ila_string = $this->ila_str_replace_once($ila_replace, $linktoquotedmsg, $ila_string);

						// At last the final step, sub in the attachment link
						$this->_message = str_replace($quotes[$i], $ila_string, $this->_message);
					}
					$which_link++;
				}
				$start += $blockquote_count;
			}
		}

		return;
	}

	/**
	 * ila_showInline()
	 *
	 * - Does the actual replacement of the [attach tag with the img tag
	 *
	 * @param mixed[] $done
	 * @param mixed[] $attachments
	 * @param int $ila_num
	 */
	private function ila_showInline($done, $attachments, $ila_num)
	{
		global $txt, $context, $modSettings, $settings;

		$images = array('none', 'img', 'thumb');

		// Expand the done array back into vars that equal the keys ... ie $id $type
		// $done = array('id' => '', 'type' => '', 'align' => '', 'width' => '');
		extract($done);
		$inlinedtext = '';

		// Find the text of the attachment being referred to, the attachment array starts at 0 but the
		// tags in the message start at 1 to make it easy for those humons, need to shift the id
		if (isset($attachments[$id - 1]))
			$attachment = $attachments[$id - 1];
		else
			$attachment = '';

		// We found an attachment that matches our attach id in the message
		if ($attachment != '')
		{
			// We need a unique css id for javascript to find the correct image, cant just use the
			// attach id since we allow the users to use the same attachment many times in the same post.
			$uniqueID = $attachment['id'] . '-' . $ila_num;

			if ($attachment['is_image'])
			{
				// Make sure we have the javascript call set, for admins who turn off
				// thumbnails and set a max width and other crazy stuff
				if (!isset($attachment['thumbnail']['javascript']))
				{
					if (((!empty($modSettings['max_image_width']) && $attachment['real_width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachment['real_height'] > $modSettings['max_image_height'])))
					{
						if (isset($attachment['width']) && isset($attachment['height']))
							$attachment['thumbnail']['javascript'] = 'return reqWin(\'' . $attachment['href'] . ';image\', ' . ($attachment['width'] + 20) . ', ' . ($attachment['height'] + 20) . ', true);';
						else
							$attachment['thumbnail']['javascript'] = 'return expandThumb(' . $attachment['href'] . ');';
					}
					else
						$attachment['thumbnail']['javascript'] = 'return ILAexpandThumb(' . $uniqueID . ');';
				}

				// Set up our private js call if needed, taken from display.php but with our ilaexpandthumb replacement
				if (!empty($attachment['thumbnail']['has_thumb']))
				{
					// If the image is too large to show inline, make it a popup window.
					if (((!empty($modSettings['max_image_width']) && $attachment['real_width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachment['real_height'] > $modSettings['max_image_height'])))
						$attachment['thumbnail']['javascript'] = $attachment['thumbnail']['javascript'];
					else
						$attachment['thumbnail']['javascript'] = 'return ILAexpandThumb(' . $uniqueID . ');';
				}
			}

			// Fix any tag option incompatabilities
			if (!empty($modSettings['ila_alwaysfullsize']))
				$type = 'img';

			// Cant show an image for a non image attachment
			if ((!$attachment['is_image']) && (in_array($type, $images)))
				$type = 'url';

			// Create the image tag based off the type given
			switch ($type)
			{
				// [attachimg=xx -- full sized image type=img
				case 'img':
					// Make sure the width its not bigger than the actual image or bigger than allowed by the admin
					if ($width != '')
						$width = !empty($modSettings['max_image_width']) ? min($width, $attachment['real_width'], $modSettings['max_image_width']) : min($width, $attachment['real_width']);
					else
						$width = !empty($modSettings['max_image_width']) ? min($attachment['real_width'], $modSettings['max_image_width']) : $attachment['real_width'];

					$ila_title = isset($context['subject']) ? $context['subject'] : (isset($attachment['name']) ? $attachment['name'] : '');

					// Insert the correct image tag, clickable or just a full image
					if ($width < $attachment['real_width'])
						$inlinedtext = '<a href="' . $attachment['href'] . ';image" id="link_' . $uniqueID . '" onclick="' . $attachment['thumbnail']['javascript'] . '"><img src="' . $attachment['href'] . ';image" alt="' . $uniqueID . '" title="' . $ila_title . '" id="thumb_' . $uniqueID . '" style="width:' . $width . 'px;border:0;" /></a>';
					else
						$inlinedtext = '<img src="' . $attachment['href'] . ';image" alt="" title="' . $ila_title . '" id="thumb_' . $uniqueID . '" style="width:' . $width . 'px;border:0;" />';
					break;
				// [attach=xx] or depreciated [attachthumb=xx]-- thumbnail
				case 'none':
					// If a thumbnail is available use it, if not create one and use it
					if ($width != '' && $attachment['thumbnail']['has_thumb'])
						$width = min($width, isset($attachment['real_width']) ? $attachment['real_width'] : (isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160));
					elseif ($attachment['thumbnail']['has_thumb'])
						$width = isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160;
					elseif ($width != '')
						$width = min($width, isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160, $attachment['real_width']);
					else
						$width = min(isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160, $attachment['real_width']);

					$ila_title = isset($context['subject']) ? $context['subject'] : (isset($attachment['name']) ? $attachment['name'] : '');

					// Now with the width defined insert the thumbnail if available or create the
					// 'fake' html resized thumb
					if ($attachment['thumbnail']['has_thumb'])
						$inlinedtext = '<a href="' . $attachment['href'] . ';image" id="link_' . $uniqueID . '" onclick="' . $attachment['thumbnail']['javascript'] . '"><img src="' . $attachment['thumbnail']['href'] . '" alt="' . $uniqueID . '" title="' . $ila_title . '" id="thumb_' . $uniqueID . '"  style="width:' . $width . 'px;border:0;" /></a>';
					else
						$inlinedtext = $this->ila_createfakethumb($attachment, $width, $uniqueID);
					break;
				// [attachurl=xx] -- no image, just a link with size/view details type = url
				case 'url':
					$inlinedtext = '<a href="' . $attachment['href'] . '"><img src="' . $settings['images_url'] . '/icons/clip.gif" align="middle" alt="*" border="0" />&nbsp;' . $attachment['name'] . '</a> (' . $attachment['size'] . ($attachment['is_image'] ? '. ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . ' - ' . $txt['attach_viewed'] : ' - ' . $txt['attach_downloaded']) . ' ' . $attachment['downloads'] . ' ' . $txt['attach_times'] . '.)';
					break;
				// [attachmini=xx] -- just a plain link type = mini
				case 'mini':
					$inlinedtext = '<a href="' . $attachment['href'] . '"><img src="' . $settings['images_url'] . '/icons/clip.gif" align="middle" alt="*" border="0" />&nbsp;' . $attachment['name'] . '</a>';
					break;
			}

			// Handle the align tag if it was supplied, should move this to CSS yes
			if ($align == 'left' || $align == 'right')
				$inlinedtext = '<div style="float: ' . $align . ';margin: .5ex 1ex 1ex 1ex;">' . $inlinedtext . '</div>';
			elseif ($align == 'center')
				$inlinedtext = '<div style="width: 100%;margin: 0 auto;text-align: center">' . $inlinedtext . '</div>';

			// Keep track of the attachments we have in-lined so we can exclude them from being displayed in the post footers
			$this->_ila_dont_show_attach_below[$attachment['id']] = 1;
		}
		else
		{
			// couldn't find the attachment specified
			// - they may have specified it wrong
			// - or they don't have permissions for attachments
			// - or they are replying to a message and this is in a quote, code or other type of tag
			// - or it has not been uploaded yet because they are previewing a new message,
			// - or they are modifiying a message and added new attachments and hit preview
			// .... simple huh?
			if (allowedTo('view_attachments'))
			{
				// Check to see if the preview flag, via attach number, is set, if so try to render a preview ILA
				if (isset($this->_ila_new_msg_preview[$id]))
					$inlinedtext = $this->ila_preview_inline($this->_ila_new_msg_preview[$id], $type, $id, $align, $width);
				else
					$inlinedtext = $txt['ila_attachment_missing'];
			}
			else
				$inlinedtext = $txt['ila_forbidden_for_guest'];
		}

		return $inlinedtext;
	}

	/**
	 * ila_createfakethumb()
	 *
	 * Creates the false thumbnail if none exits
	 *
	 * @param mixed[] $attachment
	 * @param int $width
	 * @param int $uniqueID
	 */
	private function ila_createfakethumb($attachment, $width, $uniqueID)
	{
		global $modSettings, $context;

		// We were requested to show a thumbnail but none exists? how embarrassing, we should hang our heads in shame!
		// So we create our own thumbnail display using html img width / height attributes on the attached image
		$dst_width = '';

		// Get the attachment size
		$src_width = $attachment['real_width'];
		$src_height = $attachment['real_height'];

		// Set thumbnail limits
		$max_width = $width;
		$max_height = min(isset($modSettings['attachmentThumbHeight']) ? $modSettings['attachmentThumbHeight'] : 120, floor($width / 1.333));

		// Determine whether to resize to max width or to max height (depending on the limits.)
		if ($src_height * $max_width / $src_width <= $max_height)
		{
			$dst_height = floor($src_height * $max_width / $src_width);
			$dst_width = $max_width;
		}
		else
		{
			$dst_width = floor($src_width * $max_height / $src_height);
			$dst_height = $max_height;
		}

		// Don't show a link if we can't resize or if we were asked not to
		$ila_title = isset($context['subject']) ? $context['subject'] : (isset($attachment['name']) ? $attachment['name'] : '');

		// Build the relacement string
		if ($dst_width < $src_width || $dst_height < $src_height)
			$inlinedtext = '<a href="' . $attachment['href'] . ';image" id="link_' . $uniqueID . '" onclick="return ILAexpandThumb(' . $uniqueID . ');"><img src="' . $attachment['href'] . '" alt="' . $uniqueID . '" title="' . $ila_title . '" style="width:' . $dst_width . 'px;height:' . $dst_height . 'border:0;" id="thumb_' . $uniqueID . '" /></a>';
		else
			$inlinedtext = '<img src="' . $attachment['href'] . ';image" alt="" title="' . $ila_title . '" border="0" />';
		return $inlinedtext;
	}

	/**
	 * ila_preview_inline()
	 *
	 * Renders a preview box for attachments that have not been uploaded, used in preview message
	 *
	 * @param string $attachname
	 * @param string $type
	 * @param int $id
	 * @param string $align
	 * @param int $width
	 */
	private function ila_preview_inline($attachname, $type, $id, $align, $width)
	{
		global $txt, $modSettings;

		// We are trying to preview a message but the attachments have not been uploaded,
		// lets sub in a fake image box with our ILA text so the user can check things are
		// positioned correctly even if they cant yet see the image
		$inlinedtext = '';
		$txt_name = 'ila_' . $type;

		// Decide how to do our fake preview based on the type
		switch ($type)
		{
			// [attachimg=xx -- full sized image type=img
			case 'img':
				if ($width != '')
					$width = !empty($modSettings['max_image_width']) ? min($width, $modSettings['max_image_width']) : $width;
				else
					$width = !empty($modSettings['max_image_width']) ? min($modSettings['max_image_width'], 400) : 160;
				$inlinedtext = '<div style="display:-moz-inline-box;display:inline-block;background: white;width:' . $width . 'px;height:' . floor($width / 1.333) . 'px;border:1px solid #000;vertical-align:bottom;">[Attachment:' . $id . ': <strong>' . $attachname . '</strong> ' . $txt[$txt_name] . ']</div>';
				break;
			// [attach=xx] or depreciated [attachthumb=xx]-- thumbnail
			case 'none':
				if ($width != '')
					$width = min($width, isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160);
				else
					$width = isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160;
				$inlinedtext = '<div style="display:-moz-inline-box;display:inline-block;background: white;width:' . $width . 'px;height:' . floor($width / 1.333) . 'px;border:1px solid #000;vertical-align:bottom;">[Attachment:' . $id . ': <strong>' . $attachname . '</strong> ' . $txt[$txt_name] . ']</div>';
				break;
			// [attachurl=xx] -- no image, just a link with size/view details type = url
			case 'url':
				$inlinedtext = '[Attachment:' . $id . ': ' . $attachname . ' ' . $txt[$txt_name] . ']';
				break;
			// [attachmini=xx] -- just a plain link type = mini
			case 'mini':
				$inlinedtext = '[Attachment:' . $id . ': ' . $attachname . ' ' . $txt[$txt_name] . ']';
				break;
		}

		// Handle the align tag if it was supplied
		if ($align == 'left' || $align == 'right')
			$inlinedtext = '<div style="float:' . $align . ';margin: .5ex 1ex 1ex 1ex;">' . $inlinedtext . '</div>';
		elseif ($align == 'center')
			$inlinedtext = '<div style="width:100%;margin:0 auto;text-align:center">' . $inlinedtext . '</div>';

		return $inlinedtext;
	}

	/**
	 * ila_load_attachments()
	 *
	 * - Loads attachments for a given msg if they have not yet been loaded
	 *
	 * @param int $msg_id
	 */
	private function ila_load_attachments($msg_id)
	{
		global $modSettings;

		if (!array($msg_id))
			$msg_id = array($msg_id);
		$attachments = array();

		// With a message id and the topic we can fetch the attachments
		if (!empty($modSettings['attachmentEnable']) && allowedTo('view_attachments', $this->_board) && $this->_topic != -1)
			$attachments = getAttachments($msg_id);

		return $attachments;
	}

	/**
	 * ila_get_topic()
	 *
	 * - Get the topic and board for a given message number, needed to check permissions
	 */
	private function ila_get_topic()
	{
		$db = database();

		// Init
		$this->_topic = -1;
		$this->_board = null;

		// No message is comlete without a topic and board, its like bread, peanut butter and jelly
		if (!empty($this->_msg_id))
		{
			$request = $db->query('', '
				SELECT
					id_topic, id_board
				FROM {db_prefix}messages
				WHERE id_msg = {int:msg}
				LIMIT 1',
				array(
					'msg' => $this->_msg_id,
				)
			);
			if ($db->num_rows($request) == 1)
				list($this->_topic, $this->_board) = $db->fetch_row($request);
			$db->free_result($request);
		}
	}

	/**
	 * ila_str_replace_once()
	 *
	 * - Looks for the first occurence of $needle in $haystack and replaces it with $replace, this is a single replace
	 *
	 * @param string $needle
	 * @param string $replace
	 * @param string $haystack
	 */
	private function ila_str_replace_once($needle, $replace, $haystack)
	{
		$pos = strpos($haystack, $needle);
		if ($pos === false)
		{
			// Nothing found
			return $haystack;
		}
		return substr_replace($haystack, $replace, $pos, strlen($needle));
	}

	/**
	 * ila_hide_bbc()
	 *
	 * Makes [attach tags invisible for certain bbc blocks like code, nobbc, etc
	 *
	 * @param string $hide_tags
	 */
	public function ila_hide_bbc($hide_tags = '')
	{
		global $modSettings;

		// Not using BBC no need to do anything
		if (empty($modSettings['enableBBC']))
			return $this->_message;

		// If our ila attach tags are nested inside of these tags we need to hide them so they don't execute
		if ($hide_tags == '')
			$hide_tags = array('code', 'html', 'php', 'noembed', 'nobbc');

		// Look for each tag, if attach is found inside then replace its '[' with a hex
		// so parse bbc does not try to render them
		foreach ($hide_tags as $tag)
		{
			if (stripos($this->_message, '[' . $tag . ']') !== false)
			{
				$this->_message = preg_replace_callback('~\[' . $tag . ']((?>[^[]|\[(?!/?' . $tag . ']))+?)\[/' . $tag . ']~i',
				function($matches) use($tag) {return "[" . $tag . "]" . str_ireplace("[attach", "&#91;attach", $matches[1]) . "[/" . $tag . "]";},
				$this->_message);
			}
		}

		return $this->_message;
	}

	/**
	 * Returns a reference to the existing instance
	 */
	public static function ila_parser($message, $cache_id)
	{
		if (self::$_ila_parser === null)
			self::$_ila_parser = new self($message, $cache_id);

		return self::$_ila_parser;
	}
}