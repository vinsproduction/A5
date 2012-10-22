// Простейшие функции преобразования структуры данных в строку и обратно
// serialize - на вход принимает объект, который нужно преобразовать в строку
// unserialize - на вход принимает строку и возвращает объект преобраованный serialize
// Функции могут правильно работать и сохранять только если значениями объекта
// являются простые типы данных, такие как: массив, хэш, строки и числа

function serialize(o, level)
{
	var s = '';
	if (level == null) { level = 0; }
	var tabs = '';
	for (i = 0; i < level; i++)	{ tabs += '\t'; }

	var type = null;
	if (o instanceof Array) { type = 'array'; }
	else { type = typeof(o); }

	if (type == 'array')
	{
		s += '\n' + tabs + '[\n';
		for (var n = 0; n < o.length; n++)
		{
			if (n > 0) { s += ',\n'; }
			s += tabs + '\t' + serialize(o[n], level + 1);
		}
		s += '\n' + tabs + ']';
	}
	else if (type == 'object')
	{
		s += '\n' + tabs +  '{\n';
		var first = true;
		for (var n in o)
		{
			if (!first) { s += ',\n'; }
			n = n.replace("'", "\\'");
			n = n.split("\n"); n = n.join("\\n");
			n = n.split("\r"); n = n.join("\\r");
			s += tabs + '\t\'' + n + '\': ' + serialize(o[n], level + 1);
			first = false;
		}
		s += '\n' + tabs + '}';
	}
	else
	{
		if (type == 'string')
		{
			var str = o;
			str = str.replace("'", "\\'");
			str = str.split("\n"); str = str.join("\\n");
			str = str.split("\r"); str = str.join("\\r");
			s += '\'' + str + '\'';
		}
		else
		{
			s += o;
		}
	}
	return s;
}

function unserialize(data)
{
	eval('var tmp = ' + data);
	return tmp;
}
