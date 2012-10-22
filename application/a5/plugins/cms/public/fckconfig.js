FCKConfig.DefaultLanguage = 'ru';

FCKConfig.EnterMode = 'br';
FCKConfig.ShiftEnterMode = 'p';
	
FCKConfig.ImageUpload = false;
FCKConfig.FlashUpload = false;
FCKConfig.LinkUpload = false;

FCKConfig.LinkDlgHideAdvanced = false;
FCKConfig.ImageDlgHideAdvanced = false;
FCKConfig.FlashDlgHideAdvanced = false;
	
FCKConfig.FloatingPanelsZIndex = 30;

FCKConfig.Plugins.Add( 'dragresizetable' );

FCKConfig.ToolbarSets["CMS"] = [
	['Source','-','Save','Preview'],
	['Cut','Copy','Paste','PasteText','PasteWord','-','Print'],
	['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
	'/',
	['Bold','Italic','Underline','StrikeThrough','-','Subscript','Superscript'],
	['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote'],
	['JustifyLeft','JustifyCenter','JustifyRight','JustifyFull'],
	['Link','Unlink','Anchor'],
	['Image','Flash','Table','Rule','SpecialChar'],
	'/',
	['FontFormat','FontName','FontSize'],
	['TextColor','BGColor'],
	['FitWindow','ShowBlocks']
];

FCKConfig.ProtectedSource.Add(/<style[\s\S]*?<\/style>/gi);

// Небольшая правка - меняем выражение чтобы удалению подлежали данные состоящие только из пробелов или nbsp и 160
FCKRegexLib.EmptyOutParagraph = /^(<(p|div|address|h\d|center|br)(?=[ >])[^>]*>)?(?:\s*|&nbsp;|&#160;)(<\/\2>)?$/;

