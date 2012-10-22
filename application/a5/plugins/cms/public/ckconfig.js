CKEDITOR.editorConfig = function(config)
{
	config.defaultLanguage = 'ru';
	config.enterMode = CKEDITOR.ENTER_BR;
	config.shiftEnterMode = CKEDITOR.ENTER_P;
	config.removePlugins = 'about,button,forms,newpage,pagebreak,preview,scayt,smiley,stylescombo,templates,wsc';
	config.protectedSource.push(/<style[\s\S]*?<\/style>/gi);
	config.baseFloatZIndex = 30;
	config.skin = 'v2';
};

CKEDITOR.on('dialogDefinition', function(ev)
{
	var dialogName = ev.data.name;
	var dialogDefinition = ev.data.definition;
	var editor = ev.editor;
	
	if (dialogName == 'link') 
	{
		var infoTab = dialogDefinition.getContents('info');
		infoTab.add(
		{
			type : 'button',
			label : 'Выбрать из документов сайта',
			id : 'site_documents',
		 	filebrowser :
		 	{
				action : 'Browse',
				target : 'info:url',
				url : editor.config.filebrowserDocumentsBrowseUrl
		 	}
		});
	}

	if (dialogName == 'image') 
	{
		var linkTab = dialogDefinition.getContents('Link');
		
		var browse = linkTab.get('browse');
		
		browse.filebrowser = 
		{
			action : 'Browse',
			target : 'Link:txtUrl',
			url : editor.config.filebrowserLinkBrowseUrl
		};
		
		var hbox = 
		{
			id : 'hboxBrowseButtons',
			type : 'hbox',
			padding: 2,
			children :
			[
			 	browse,
				{
					type : 'button',
					label : 'Выбрать из документов сайта',
					id : 'site_documents',
				 	filebrowser :
				 	{
						action : 'Browse',
						target : 'Link:txtUrl',
						url : editor.config.filebrowserDocumentsBrowseUrl
				 	}
				}
			]
		};
		
		linkTab.remove('browse');
		linkTab.add(hbox, 'cmbTarget');
	}
});