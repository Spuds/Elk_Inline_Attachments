[hr]
[center][size=16pt][b]In Line Attachments v1.0[/b][/size][/center]
[hr]

[color=blue][b][size=12pt][u]License[/u][/size][/b][/color]
This addon is released under a MPL V1.1 license, a copy of it with its provisions is included with the package.

[color=blue][b][size=12pt][u]Introduction[/u][/size][/b][/color]
This addon adds the ability to position your attachments in your post, like the IMG tag, using newly created BBC codes. This is useful for inserting attachments inline with your text.  Several types of attachments are possible such as : full-size, thumbnail or link. For each attachment you can select how and where the attachment appears within the message. You can also use the same image in multiple areas in the same post. You can of course still have the attachment at the bottom of the post as is the SMF default.

[color=blue][b][size=12pt][u]Features[/u][/size][/b][/color]
o Adds bbcodes [attachimg=n], [attach=n], [attachurl=n] or [attachmini=n] to position attachments within the post.  n is the number of the attachment in the post, eg first = 1, second = 2, etc.
o Adds optional align and width attributes as [attachimg=1 align=left width=80]
o Align can be left, right or center.  For left and right aligns the text will flow around the image
o Width works on attachimg, if the specified width is less than the image then a link (or highslide) is added to one to view the full sized image
o The mod is HS4SMF aware, if the highslide mod is installed it will work on the attachment (assuming its not full size)
o Adds in line options next to the attachment/upload box to insert the correct bbcode
o The default placement of attachments at the bottom of the post is unaffected for the attachments that were not used in line.
o Works in all areas of the board such as new posts since last visit / all posts of user / new, reply, modify messages / Topic/Reply History.
o Allows pseudo preview for attachments that have not been uploaded by providing an image box / text string shown in the preview window to help ensure proper layout and placement of attachments

There are some basic admin settings available with this mod, go to admin - configuration - addon settings - ILA.  Here you can disable/enable the addon.