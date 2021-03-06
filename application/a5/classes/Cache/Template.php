<?php
// Здесь реализован пример класса для создания движка кэша
// По данному примеру можно создать движок для работы с любым типом кэширования данных
class Cache_Template
{
	function __construct() { return true; }

	/*
	* Функция сохранения данных - должна возвращать true если данные успешно сохранены и false
	* если сохранить не удалось, на вход метод получает следующие параметры
	* $cache_id - рекомендованное фреймвоком имя ключа кэша, данное имя всегда является уникальным
	*             (по-крайней мере в теории). Способ генерации данного ключа можно увидеть в
	*             A5::cache_generate_id();
	* $data - данные для сохранения в кэше. ВНИМАНИЕ!!! Данные являются не сериализованными,
	*         поэтому движок должен сам решать каким образом их сохранять, естественно рекомендованный
	*         способ - это использование функции serialize();
	* $time - время в секундах на которое сохраняются данные в кэше.
	* $tags - массив тэгов ассоциированных с данным ключём. Это одномерный массив где ключи массива являются
	*         человекопонятными именами тэгов, а значения - соотвествующии им рекомендованные фреймвоком.
	*         Генерация рекомендованных id можно увидеть в A5::cache_generate_tag_id();
	*
	* Реализовать механизм сохранения данных
	*/
	function store($cache_id, $data, $time = 0, $tags = array()) { return true; }

	/*
	* Функция для вынимания данных из кэша, ключи вызова аналогичны методу store
	* Функция должна вернуть данные (не сериализованные) если они имеются в кэша
	* и должна вернуть null если данных в кэше нет
	*/
	function fetch($cache_id) { return null; }

	// Функция принудительного удаления данных кэша по указнному ключу
	// должна вернуть true - если данные удалены или их уже нет в кэша и false - иначе
	function delete($cache_id) { return true; }

	// Функция принудительного удаления данных кэша по указанным
	// именам тэгов, аналогично методу delete
	function delete_tags($tags) { return true; }
}