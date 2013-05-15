<?php

defined('IN_CART') or die;
/**
 *
 *
 * 数据库操作类
 *
 */
class DB {
	
	protected $conn;		//连接
	
	protected $query;		//执行sql的结果

	protected $lastSql;		//上一次执行的sql

	protected $errno;		//错误号
	
	protected $error;		//错误信息

	protected $querynum;	//查询次数

	protected $dbprefix;	//表前缀
	
	protected $dbcharset;	//字符集

	private static $instance;
	
	/**
	 *
	 *	返回一个DB
	 *
	 */
	public static function getDB() {
		if(!self::$instance) {
			global $_CONF;
			$driver = strtolower($_CONF['db']['driver']);
			if($driver == "mysql") {
				self::$instance = new DBMysql($_CONF['db']);
			} else if($driver == "pdo") {
				self::$instance = new DBPdo($_CONF['db']);
			} else if($driver == "sqlsrv") {
				self::$instance = new DBSqlsrv($_CONF['db']);
			}
			unset($_CONF['db']);
		}
		return self::$instance;
	}

	/**
	 *
	 *	操作mysql
	 *
	 */
	protected function query($sql) {
		return false;
	}
	
	/**
	 *
	 *	获取查询次数
	 *
	 */
	public function getQueryNum(){
		return $this->querynum;
	}
	
	/**
	 *
	 *	获取查询字符集
	 *
	 */
	public function getCharset(){
		return $this->dbcharset;
	}

	/**
	 *
	 *	插入操作
	 *
	 */
	public function insert($tableName,$data = array()) {
		$sql = "INSERT INTO ".$this->getTableName($tableName);
		$sql .= "(".implode(",",array_map(array($this,"escapekey"),array_keys($data))).") values(";
		$sql .= $this->getInsertVal($data);
		$sql = trim($sql,",").")";
		$this->query($sql);

		if(!$this->errno){
			return $this->lastid();
		}
		return false;
	}
	
	protected function lastid() {
		return 0;
	}

	/**
	 *
	 *	插入多条记录
	 *
	 */
	public function insertMulti($tableName,$data = array()) {
		
		if(empty($data)) return ;
		$sql = "INSERT INTO ".$this->getTableName($tableName);
		$sql .= "(".implode(",",array_map(array($this,"escapekey"),array_keys(current($data)))).") values";
		
		foreach($data as $val){
			$sql .= "(".implode(",",array_map(array($this,"escapeval"),$val))."),";
		}
	
		$sql = trim($sql,",");
		$this->query($sql);
		return $this->errno?false:true;
	}
	
	/**
	 *
	 *	获取插入值
	 *
	 */
	public function getInsertVal($data = array()) {
		$sql = "";
		foreach($data as $val) {
			$sql .= $this->escapeval($val).",";
		}
		return $sql;
	}
	
	/**
	 *
	 *	替换操作
	 *
	 */
	public function replace($tableName,$data = array()) {
		
		$sql = "REPLACE INTO ".$this->getTableName($tableName);
		$sql .= "(".implode(",",array_map(array($this,"escapekey"),array_keys($data))).") values(";
		
		$sql .= $this->getInsertVal($data);

		$sql = trim($sql,",").")";
	
		$this->query($sql);
		
		return !$this->errno ? true : false;
	}
	
	/**
	 *
	 *	替换多条记录
	 *
	 */
	public function replaceMulti($tableName,$data = array()) {
		
		if(empty($data)) return ;

		$sql = "REPLACE INTO ".$this->getTableName($tableName);
		$sql .= "(".implode(",",array_map(array($this,"escapekey"),array_keys(current($data)))).") values";
		
		foreach($data as $val){
			$sql .= "(".implode(",",array_map(array($this,"escapeval"),$val))."),";
		}
	
		$sql = trim($sql,",");
		$this->query($sql);
		return $this->errno?false:true;
	}
	
	/**
	 *
	 * 查询操作，$keyid判断返回数组的key
	 *
	 */
	public function select($tableName,$fields,$where = "",$orderby = "",$limit= "",$keyid = "",$distinct = false) {
		if($fields == "*") {
			$fieldstr = "*";
		} elseif($distinct) { //唯一，只取一个元素，selectcol使用
			$fieldstr = "distinct ".$this->escapekey($fields);	
		} else {
			$fieldstr = implode(",",array_map(array($this,"escapekey"),explode(",",$fields)));
		}
		$sql = "SELECT $fieldstr "
			 . "FROM ".$this->getTableName($tableName)
			 . $this->buildWhere($where)
			 . $this->buildOrderBy($orderby)
			 . $this->buildLimit($limit)
			 ;
		$this->query($sql);
		return $this->getResults($keyid);
	}
	
	/**
	 *
	 * 查询操作，返回key->val一维数组
	 *
	 */
	 public function selectkv($tableName,$keyid,$valueid,$where = "",$orderby = "",$limit = "") {
		$temp = $this->select($tableName,"$keyid,$valueid",$where,$orderby,$limit,$keyid);
		$result = array();
		if($temp) {
			foreach($temp as $key=>$val) {
				$result[$val[$keyid]] = $val[$valueid];
			}
		}
		return $result;
	 }

	/**
	 *
	 * 删除操作
	 *
	 */
	public function delete($tableName,$where,$only = false) {
		$sql	  = "DELETE FROM ".$this->getTableName($tableName)." "
				  . $this->buildWhere($where)
				  . ($only?" LIMIT 1":"")
			      ;
		$this->query($sql);
		return $this->errno?false:true;
	}
	
	/**
	 *
	 * 更新操作
	 *
	 */
	public function update($tableName,$data,$where,$only = false) {
		$sql = "UPDATE ".$this->getTableName($tableName)." SET ";
		if(is_array($data)) {
			foreach($data as $key=>$val) {
				$sql .= $this->escapekey($key) . "=" . $this->escapeval($val).",";
			}
		} else {
			$sql .= $data;
		}
		
		$sql = rtrim($sql,",")." "
			 . $this->buildWhere($where)
			 . ($only?" LIMIT 1":"")
			 ;
		
		$this->query($sql);
		return $this->errno?false:true;
	}
	
	/**
	 *
	 * 更新bool值
	 *
	 */
	public function updatebool($tableName,$field,$where) {
		$sql = "UPDATE ".$this->getTableName($tableName)
			 . " SET $field=if($field,0,1) "
			 . $this->buildWhere($where)
			 ;
		$this->query($sql);
		return $this->errno?false:true;
	}
	
	/**
	 *
	 * 更新数字
	 *
	 */
	public function updatecre($tableName,$field,$where,$type = "incre",$step = 1) {
		
		$sql = "UPDATE ".$this->getTableName($tableName) 
			 . " SET $field = $field " . (($type == "incre") ? "+":"-") . $step
			 . $this->buildWhere($where)
			 ; 
		$this->query($sql);
		return $this->errno?false:true;
	}
	/**
	 *
	 * 更新数字
	 *
	 */
	public function updatecremulti($tableName,$fieldarr,$where) {
		$setsql = ' SET';
		foreach($fieldarr as $field=>$oper) {
			if(preg_match("/^(\+|-) /",$oper)) {
				$setsql .= " `$field` = $field $oper,";
			} else {
				$setsql .= " `$field` = '$oper',";
			}
		}
		$setsql = trim($setsql,",");

		$sql = "UPDATE ". $this->getTableName($tableName)
			 . $setsql
			 . $this->buildWhere($where)
			 ;
		$this->query($sql);
		return $this->errno ? false : true;
	}

	

	/**
	 *
	 * 过滤值
	 *
	 */
	private function escapeval($val) {
		if(is_string($val)) {
			$val = '\''. $val . '\'';
		} elseif(is_array($val)) {
			$val = array_map(array($this,"escapeval"),$val);
		} elseif(is_bool($val)) {
			$val = $val?'1':'0';
		} elseif(is_null($val)) {
			$val =  'null';
		} 
		return $val;
	}
	
	
	/**
	 *
	 * 过滤字段
	 *
	 */
	private function escapekey($key) {
		return '`'.$key.'`';
	}
	
	/**
	 *
	 * 选择一行数据
	 *
	 */
	public function selectrow($tableName,$fields,$where = '',$order = '') {
		$ret = $this->select($tableName,$fields,$where,$order,"1");
		return $ret?current($ret):array();
	}
	
	/**
	 *
	 * 选择一列数据
	 *
	 */
	public function selectcol($tableName,$field,$where,$order = array(),$keyid="",$distinct = false) {
		$ret = $this->select($tableName,$field.($keyid?",$keyid":""),$where,$order,"","",$distinct);
		$returnarr = array();
		if($keyid) {
			foreach($ret as $key=>$val) {
				$returnarr[$val[$keyid]] = $val[$field];
			}
		} else {
			foreach($ret as $key=>$val) {
				$returnarr[] = $val[$field];
			}
		}
		return $returnarr;
	}
	
	/**
	 *
	 * 选择一个值
	 *
	 */
	public function selectval($tableName,$field,$where,$order='',$default = '') {
		$ret = $this->selectrow($tableName,$field,$where,$order);
		return $ret ? $ret[$field]:$default;
	}
	
	/**
	 *
	 * 查询max,min,count
	 *
	 */
	public function selectgval($tableName,$type,$field = '',$where = '',$distinct = false) {
		
		$field = $this->getTypeField($type,$field,$distinct);
		$sql = "SELECT $field"
		     . " FROM ".$this->getTableName($tableName)
			 . " ".$this->buildWhere($where)
			 ;
		$this->query($sql);
		$row = $this->getResults('','row');
		$val = 0;
		if(!empty($row)) $val = current($row);
		return $val;
	}
	
	/**
	 *
	 * 查询count
	 *
	 */
	public function selectcount($tableName,$where = '',$field = '',$distinct = false) {
		return $this->selectgval($tableName,'count',$field,$where,$distinct);
	}
	
	/**
	 *
	 * 查询max
	 *
	 */
	public function selectmax($tableName,$field = '',$where = '') {
		return $this->selectgval($tableName,'max',$field,$where);
	}
	
	/**
	 *
	 * 查询min
	 *
	 */
	public function selectmin($tableName,$where = '') {
		return $this->selectgval($tableName,'min',$field,$where);
	}

	/**
	 *
	 * 直接执行sql
	 *
	 */
	 public function selectsql($sql,$returntype = 'list',$field = '') {
		
		$this->query($sql);
		$sqlret = $this->getResults("");
		if($returntype == "list") {		//返回一个二维list
			return $sqlret;
		} elseif($returntype == "col") { //返回一列
			$ret = array();
			foreach($sqlret as $v) {
				$ret[] = $field == '' ? current($v) : $v[$field];
			}
			return $ret;
		} elseif($returntype == "row") { //返回一行
			return current($sqlret);
		} elseif($returntype == "var") { //返回一个值
			$ret = current($sqlret);
			return current($ret);
		}
	 }

	/**
	 *
	 * 获取需要查询的字段
	 *
	 */
	private function getTypeField($type,$field,$distinct = false) {
		$type = strtolower($type);
		if($type == "max") {
			$field = "max(`$field`) as maxval";
		} else if($type == "min") {
			$field = "min(`$field`) as minval";
		} else if($type == "count") {
			if($field) {
				return $distinct?"count(distinct(`$field`)) as countval":"count(`$field`) as countval";
			} else {
				return "count(1) as countval";
			}
		}
		return $field;
	}
	
	/**
	 *
	 * 查询分组
	 *
	 */
	public function selectgroup($tableName,$groupby,$field,$type ='',$typefield='',$where='',$keyid='') {
		$sql = "SELECT ".$this->escapekey($field).",".$this->getTypeField($type,$typefield)
			 . " FROM ".$this->getTableName($tableName)
			 . " ".$this->buildWhere($where)
			 . " GROUP BY ".$this->escapekey($groupby)
			 ;
		$this->query($sql);
		
		return $this->getResults($keyid);
	}
	
	/**
	 *
	 * 判断数据库中是否存在
	 *
	 */
	public function selectexist($tableName,$where = "") {
		return $this->selectrow($tableName,'*',$where)?true:false;
	}


	
	/**
	 *
	 * 获取表连接count
	 *
	 */
	public function joincount($lname,$rname,$para = array(),$where = array(),$field = '',$distinct = false) {
		$lname		= $this->getTableName($lname);
		$rname		= $this->getTableName($rname);

		$jtype	    = isset($para["jtype"])?strtoupper(trim($para['jtype'])):'INNER';  
		$onarr		= cstrpos($para["on"],",")?explode(",",$para["on"]):array($para["on"],$para["on"]);
		$countstr		= "";
		if($field) {
			$countstr = $distinct?"count(distinct(a.`$field`)) as countstr":"count(a.`$field`) as countstr";
		} else {
			$countstr = "count(1) as countstr";
		}

		$sql = "SELECT $countstr "
			 . "FROM $lname a "
			 . "$jtype JOIN $rname b "
			 . "ON a.".$this->escapekey($onarr[0])." = b.".$this->escapekey($onarr[1])
			 . $this->buildJoinWhere($where)
			 ;
		
		$this->query($sql);
		
		$row = $this->getResults('','row');
		if($row) $val = current($row);
		
		return $val === null?0:$val;
	}
	
	public function joinrow($lname,$rname,$para = array(),$fields = '',$where = array()) {
		$ret = $this->join($lname,$rname,$para,$fields,$where);
		return current($ret);
	}

	/**
	 *
	 * 连接操作
	 *
	 */
	public function join($lname,$rname,$para,$fields = array(),$where,$orderby = array(),$limit= "",$keyid = "",$distinct = false) {
		
		$larr	 = isset($fields["a"]) ? explode(",",$fields["a"]):array();
		$rarr    = isset($fields["b"]) ? explode(",",$fields["b"]):array();

		$jtype	    = isset($para["jtype"])?strtoupper(trim($para['jtype'])):'INNER';
		$onarr		= cstrpos($para["on"],",")?explode(",",$para["on"]):array($para["on"],$para["on"]);
		

		$lname	= $this->getTableName($lname);
		$rname  = $this->getTableName($rname);
		$fieldstr   = $distinct ? " DISTINCT " : "";
		if($larr) {
			foreach($larr as $key=>$val){
				if(preg_match("/\bas\b/i",$val)) {
					$fieldstr .= "a." . preg_replace("/^([\w]+)/",$this->escapekey("$1"),$val) . ",";
				} else {
					$fieldstr .= "a.".($val == "*"?$val:$this->escapekey($val)).",";	
				}
			}
		}
		if($rarr) {
			foreach($rarr as $key=>$val){
				if(preg_match("/\bas\b/i",$val)) {
					$fieldstr .= "b." . preg_replace("/^([\w]+)/i",$this->escapekey("$1"),$val) . ",";
				} else {
					$fieldstr .= "b.".($val == "*"?$val:$this->escapekey($val)).",";
				}
			}
		}
		
		
		$fieldstr = rtrim($fieldstr,",");
		
		$sql = "SELECT $fieldstr "
			 . "FROM $lname a "
			 . "$jtype JOIN $rname b "
			 . "ON a.".$this->escapekey($onarr[0])." = b.".$this->escapekey($onarr[1])
			 . $this->buildJoinWhere($where)
			 . $this->buildJoinOrderby($orderby)
			 . $this->buildLimit($limit)
			 ;
		$this->query($sql);
		
		return $this->getResults($keyid);
	}
	
	/**
	 *
	 * 组合limit
	 *
	 */
	private function buildLimit($limit) {
		if(!$limit) return "" ;
		return is_array($limit) ? " LIMIT {$limit[0]},{$limit[1]}" : " LIMIT $limit";
	}
	
	/**
	 *
	 * 组合order by
	 *
	 */
	private function buildOrderBy($orderby) {
		
		if(!$orderby) return ;
		$orderbystr = " ORDER BY ";
		
		is_string($orderby) && ($orderby = explode(",",$orderby));

		foreach($orderby as $key=>$val) {
			$val = trim($val);
			if(strpos($val," ")!==false) {
				$temp = explode(" ",$val);
				$orderbystr .= $this->escapekey($temp[0])." ".strtoupper($temp[1]).",";
			} else {
				$orderbystr .= $this->escapekey($val)." ASC,";
			}
		}
		$orderbystr = trim($orderbystr,",");
		return $orderbystr;
	}
	
	/**
	 *
	 * 组合where
	 *
	 */
	private function buildWhere($where) {

		if(!$where) return ;
		$constr   = !isset($where["constr"])?"AND":$where["constr"];
		$wheresql = " WHERE 1=1";
		if(is_array($where)) {
			foreach($where as $key=>$val) {
				$wheresql .= " ".$constr." ";
				if(preg_match("/\bin\b|\blike\b/i",$val)) {
					$wheresql .= $this->escapekey($key) . "   " . $val;
				} else {
					$wheresql .= $this->escapekey($key) ." = ".$this->escapeval($val);
				}
			}
		} else {
			$wheresql .= " AND $where";
		}
		return trim($wheresql,$constr);
	}
		
	/**
	 *
	 * 组合表连接where
	 *
	 */
	private function buildJoinWhere($where) {
		if(!$where) return '';
		if(is_string($where)) return " WHERE $where";

		$wheresql = " WHERE 1=1 ";
		$constr   = !isset($where["constr"])?"AND":$where["constr"];
		if(isset($where["a"])) {
			if(is_string($where["a"])) {
				$wheresql .= " ".$constr." a.".$where["a"];
			} else if(is_array($where["a"])) {
				foreach($where["a"] as $key=>$val) {
					$wheresql .= " ".$constr." ";
					if(preg_match("/\bin\b|\blike\b/i",$val)) {
						$wheresql .= "a.".$this->escapekey($key)." ".$val;
					} else {
						$wheresql .= "a.".$this->escapekey($key)."=".$this->escapeval($val);
					}
				}
			}
			
		}
		if(isset($where["b"])) {
			if(is_string($where["b"])) {
				$wheresql .= " ".$constr." b.".$where["b"];
			} else if(is_array($where["b"])) {
				foreach($where["b"] as $key=>$val) {
					$wheresql .= " ".$constr." ";
					if(preg_match("/\bin\b|\blike\b/i",$val)) {
						$wheresql .= "b.".$this->escapekey($key)." ".$val;
					}else{
						$wheresql .= "b.".$this->escapekey($key)."=".$this->escapeval($val);
					}
				}
			}
		}
		$wheresql = rtrim($wheresql,$constr);
		return $wheresql;
	}
	
	/**
	 *
	 * 组合表连接order by
	 *
	 */
	private function buildJoinOrderby($orderby) {
		if(!$orderby) return '';
		if(is_string($orderby)) return  " ORDER BY a.$orderby";
		$orderbystr = '';
		if($orderby) {
			$orderbystr = " ORDER BY ";
			if(isset($orderby["a"])) {
				$larr  = explode(",",$orderby["a"]);
				foreach($larr as $key=>$val) {
					if(strpos($val," ")!==false) {
						$temp = explode(" ",$val);
						$orderbystr .= "a.".$this->escapekey($temp[0])." ".$temp[1].",";
					} else {
						$orderbystr .= "a.".$this->escapekey($val).",";
					}
				}
			}
			
			if(isset($orderby["b"])) {
				$rarr = explode(",",$orderby["b"]);
				foreach($rarr as $key=>$val) {
					if(strpos($val," ")!==false) {
						$temp = explode(" ",$val);
						$orderbystr .= "b.".$this->escapekey($temp[0]). " ".$temp[1].",";
					} else {
						$orderbystr .= "b.".$this->escapekey($val).",";
					}
				}
			}
			$orderbystr = trim($orderbystr,",");
		}
		return $orderbystr;
	}
	

	/**
	 *
	 * 上次执行的sql
	 *
	 */
	public function getLastSql() {
		return $this->lastSql;
	}
	
	/**
	 *
	 * 查询影响的条数
	 *
	 */
	public function getAffectedRows() {
		return 0;
	}
	
	/**
	 *
	 * 返回错误
	 *
	 */
	public function getError() {
		return $this->error;
	}
	
	/**
	 *
	 * 返回错误
	 *
	 */
	public function getErrno() {
		return $this->errno;
	}
	
	/**
	 *
	 * 表名
	 *
	 */
	public function getTableName($tableName) {
		return $this->dbprefix.$tableName;
	}
	
	/**
	 *
	 * 表前缀
	 *
	 */
	public function getTablePrefix() {
		return $this->dbprefix;
	}
	
}