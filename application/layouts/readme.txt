В данной папке размещаются "раскладки" сайта.
Раскладки это те же самые вьюшки, за исключением того что они должны включать вьюшку директивой include_view().
Вы можете влиять на поведение раскладки из текущего контроллера, т.к. контроллер включается ДО раскладки и соответственно вьюшки.
Если существует раскладка с таким же именем как контроллер - то она подключиться.
В контроллере с помощью функции layout($layout_name) вы можете изменить подключаемый layout или запретить его использование вызвав layout(false).
В общем случае механизм выглядит так

include(controller_file_path);
include(layout_file_path)
{ 
   include_view()
}