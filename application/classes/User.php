<? 

Class User extends QueryBuilder {

	protected $table = 'users';

	function __construct(){
	
	}
	
	function set()
	{
		$query = self::getTable($this->table,'U')
				->select("U.*");	
				
		$this->query = $query;
		return $this;
	}
	

	function infoShort($id) {
	
		return self::getTable($this->table,'U')
				->select("U.id,U.email,U.pass")
				->where(array("U.id"=>$id))
				->execute('row');
	}

	static function info($id) {
	
		return self::set()
				->where(array("U.id"=>$id))
				->execute('row');
	}



























}