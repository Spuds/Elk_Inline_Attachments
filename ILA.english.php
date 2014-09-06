<?php
		
// ILA in line attachment admin settings
$txt['mods_cat_modifications_ila'] = 'In Line Attachments';
$txt['ila_desc'] = 'This section allows you to setup the In Line Attachment modification';
$txt['ila_enabled'] = '(ILA) Enable the in line attachment modification';
$txt['ila_basicmenu'] = '(ILA) Only show basic ILA menu';

// ILA message viewing
$txt['ila_invalid'] = ' [ Attachment Invalid Or Does Not Exist ] ';
$txt['ila_disabled'] = ' [ Attachments Disabled ] ';
$txt['ila_forbidden_for_guest'] = ' [ Guests cannot view attachments ] ';
$txt['ila_nopermission'] = ' [ You are not authorized to view attachments ] ';
$txt['ila_attachment_missing'] = ' [ Specified attachment is not available ]';
$txt['ila_quote_link'] = ' (Attachment Link) ';
$txt['ila_quote_nolink'] = ' (Attachment) ';

// Post settings (when I implement the post interface)
$txt['ila_insert'] = 'Insert Attachment';
$txt['ila_title'] = 'End-of-post expandable thumbnail ';
$txt['ila_option1'] = 'Inline expandable thumbnail';
$txt['ila_option2'] = 'Inline full-size image';
$txt['ila_option3'] = 'Inline text link';
$txt['ila_option4'] = 'Inline short text link';
$txt['ila_insert_next'] = ' in the message';
$txt['ila_thereare'] = 'There are only';
$txt['ila_attachment'] = 'attachment(s)';
$txt['ila_none'] = 'as expandable thumbnail';
$txt['ila_img'] = 'as full-size graphic';
$txt['ila_url'] = 'as a link';
$txt['ila_mini'] = 'as a compact link';

$helptxt['ILA_InLineAttachments_help'] = '<b>Add an inline attachment</b><br />
		Example:
		<br /><b>[attachimg=1 align=left width=400]</b>
		<br />This will show a left-aligned image resized to 400 pixels wide with the post text flowing around it. Execpt for the attachment tag and its number all other parameters are optional
		<br /><b>[attach=1]</b>
		<br />This will show the attachment as a thumbnail if available, if no thumbnail is available it will use a full sized image.  The image will be in line with the text of your post.
		<br /><br />
		<b>Options:</b>
		<br />attach=x, attachimg=x, attachurl=x, attachmini=x where x is the attachment number
		<br />align=left, center, right
		<br />width=### (number in pixels)
		<br />
        <h3>Modes available</h3>
        <p>
            You can choose the inline mode you want for your attachment:
            <ul>
                <li><u>Thumbnail [attach=x]</u> Your image will be shown as a thumbnail</li>
                <li><u>Full-size Image [attachimg=x]</u> Your image will be shown using its full dimensions up to the viewing size allowed by the forum.  Images larger than the viewing size allowed by the forum will be displayed at the forum maximum and be click-able to full size</li>
                <li><u>Text Link [attachurl=x]</u> Only a link is show with size and view details. By clicking on it, the image is displayed.</li>
                <li><u>Short Text Link [attachmini=x]</u> Only a link is shown without any details. By clicking on it, the image is displayed.</li>
            </ul>
        </p><br />
		<p>
			You can choose how to align the inline image:
			<ul>
				<li><u>align=left</u> The image is aligned to the left and the text will flow around it</li>
				<li><u>align=right</u> The image is aligned to the right and the text will flow around it</li>
				<li><u>align=center</u> The image is centered and the text will be below it</li>
			</ul>
		</p><br />
		<p>
			You can choose how wide to show the image:
			<ul>
				<li><u>width=123</u> The image is displayed 123pixels wide</li>
				<li>If the width specified is larger than the image or larger than the forum allows the largest allowable width will be used</li>
				<li>Can be used to shrink a thumbnail as well [attach=1 width=50] will display a 50px wide thumbnail
			</ul>
		</p><br />
        <h3>How to use it?</h3>
        <p>
            <h4>Step 1 :</h4>
            In the file selection, select the attachment file you want to upload;
            <h4>Step 2 :</h4>
            Select the inline mode, if any, you want using the list box "Inline modes";
            <h4>Step 3 :</h4>
            Repeat the steps for each attachment you want to upload. Move and edit the tags in the message body to suit your needs.
        </p>';