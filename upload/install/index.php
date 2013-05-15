<?php

//安装
define("IN_CART",	 true);
define("C_VER","1.0");//定义版本
define("C_RELEASE","20121219");
define("SITEPATH",		dirname(dirname(__FILE__)));
define("INSTALLPATH",	SITEPATH."/install");
define("COMMONPATH",	SITEPATH."/include/common");
define("DATADIR",		SITEPATH."/data");

//设置
date_default_timezone_set("Asia/Shanghai");
@ini_set("memory_limit",'64M');
@ini_set('session.cache_expire',  180);
@ini_set('session.use_trans_sid', 0);
@ini_set('session.use_cookies',   1);
@ini_set('session.auto_start',    0);

//加载公用
require COMMONPATH."/global.function.php";

//转义
if(!get_magic_quotes_gpc()) {
	if(!empty($_GET)) {
		$_GET  = caddslashes($_GET);
	}
	if(!empty($_POST)) {
		$_POST = caddslashes($_POST);
	}
	$_COOKIE  = caddslashes($_COOKIE);
	$_REQUEST = caddslashes($_REQUEST);
}

require_once INSTALLPATH."/function_install.php";
require_once INSTALLPATH."/install_lang_message.php";


$step	= isset($_REQUEST["step"])?intval($_REQUEST["step"]):1;
$errors = array();

if($step >4 || $step < 1 || ($step>1 && !ispostreq())  ) {
	$step   = 0;
	$errors = __("ACCESS_ERROR");
}
$insret	= false;

//生成安装lock文件
if(file_exists(DATADIR . "/lock/install.lock") && ($step != 4) ) {
	$errors = __("LOCK_EXIST");
	$step	= 0;
} else {
	//安装步骤
	switch($step) {
		case 2:
			//验证
			if(PHP_VERSION < '5.2') {				//php 版本过低
				$errors[] = __('PHP_VERSION_OLDER');
			}

			if(!@ini_get("file_uploads")) {			//文件上传
				$errors[] = __("FILE_UPLOAD_UNSUPPORT");
			}

			if(!extension_loaded('mysql')) {		//php，mysql扩展
				$errors[] = __("PHP_EXTENTION_UNLOAD_MYSQL");
			}
			
			if(!extension_loaded("gd")) {			//gd
				$errors[] = __("PHP_EXTENTION_UNLOAD_GD");
			}

			if(!extension_loaded("mbstring")) {		//mbstring
				$errors[] = __("PHP_EXTENTION_UNLOAD_MBSTRING");
			}

			if(!function_exists("mysql_connect")) { //mysql函数
				$errors[] = __("MYSQL_UNSUPPORT");
			}
			
			//设置data目录的写权限
			setMod(DATADIR);
			//判断可写
			if(!is_writable(DATADIR)) {
				$errors[] = __("DATA_DIR_NOT_WRITABLE");
			}
			
			setMod(SITEPATH."/uploads");
			if(!is_writable(SITEPATH."/uploads")) {
				$errors[] = __("UPLOADS_DIR_NOT_WRITABLE");
			}
			if(!is_writable("../")) {
				$errors[] = __("ROOT_NOT_WRITABLE");
			}
			break;
		case 3://导入sql
	
			$dbhost			= !empty($_POST["dbhost"]) ? trim($_POST["dbhost"]) : "localhost" ;
			$dbport			= !empty($_POST["dbport"]) ? trim($_POST["dbport"]) : "3306";
			$driver			= trim($_POST["driver"]);
			
			$dbuser			= trim($_POST["dbuser"]);
			$dbpass			= trim($_POST["dbpass"]);
			$dbname			= trim($_POST["dbname"]);
			$dbprefix		= trim($_POST["dbprefix"]);

			$mallname		= trim($_POST["mallname"]);
			$uname			= trim($_POST["adminname"]);
			$pass			= trim($_POST["adminpass"]);
			$pass2			= trim($_POST["adminpass2"]);
			$email			= trim($_POST["email"]);
			
			$adminfile		= trim($_POST["adminfile"]);
			!$adminfile && $adminfile = 'admin';

			$test			= isset($_POST["test"]) ? true : false;
			$conn			= false;
			
			if($driver == "pdo") {
				if(!extension_loaded("pdo")) {
					$errors = __("PHP_EXTENTION_UNLOAD_PDO");
					break;
				}
				if(!extension_loaded("pdo_mysql")) {
					$errors = __("PHP_EXTENTION_UNLOAD_PDOMYSQL");
					break;
				}
			}
			//创建数据库
			$conn = @mysql_connect($dbhost.":".$dbport,$dbuser,$dbpass);
			if(!$conn) {
				$errors = __("CANNT_CONNECT_MYSQL_HOST");
				break;
			}
			mysql_query("SET names utf8");

			//判断mysql版本
			$mysql_ver = @mysql_get_server_info($conn);
			if($mysql_ver < '5.0') {
				$errors = __("MYSQL_VERSION_OLDER");
				break;
			}
			
			if(!create_database($dbname)) {
				$errors = __("CANNT_CREATE_DATABASE");
				break;
			};

			//导入sql文件
			if(!install_db($dbprefix)) {
				$errors = __("IMPORT_SQLFILE_ERROR");
				break;
			}
			
			//插入配置文件
			if(!insert_config($mallname,$email,$adminfile,$dbprefix)) {
				$errors = __("ADD_DBCONFIG_ERROR");
				break;
			}

			//新建管理员
			if(!create_admin($uname,$pass,$email,$dbprefix)) {
				$errors = __("ADD_ADMIN_ERROR");
				break;
			}

			//生成配置文件
			if(!create_config($dbhost,$dbport,$dbuser,$dbpass,$dbname,$dbprefix,$driver)) {
				$errors = __("CREATE_CONFIGFILE_ERROR");
				break;
			}
			
			//是否安装体验数据
			if($test && !install_test($dbprefix)) {
				$errors = __("INSTALL_TEST_ERROR");
				break;
			}
			
			//生成lock文件
			if(!create_lock()) {
				$errors = __("TOUCH_LOCK_ERROR");
			}
			$insret			= true;
			break;
		case '4'://删除安装目录
			exit(deldir(INSTALLPATH)?"success":"failure");
			break;
	}
}



?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Yuncart安装后台</title>
<link rel="stylesheet" href="install.css" type="text/css" />
<script type="text/javascript" src="../template/jslib/jquery.js"></script>
</head>
<body>

<div class="installdiv">
		<?php if($step == 1) { ?>
			<form action="index.php?step=2" method="post" name="theform" id="theform">
					<div class="install">
						<h1>注册协议</h1>
						<textarea>版权所有 (C)2012，Yuncart.com 保留所有权利。

Yuncart是由Yuncart项目组独立开发的程序，基于PHP脚本和MySQL数据库。本程序源码开放的，任何人都可以从互联网上免费下载，并可以在不违反本协议规定的前提下进行使用而无需缴纳程序使用费。

为了使你正确并合法的使用本软件，请你在使用前务必阅读清楚下面的协议条款：

一、本授权协议适用且仅适用于Yuncart任何版本，Yuncart官方拥有对本授权协议的最终解释权和修改权。

二、协议许可的权利和限制
1、您可以在完全遵守本最终用户授权协议的基础上，将本软件应用于非商业用途，而不必支付软件版权授权费用，但我们也不承诺对个人用户提供任何形式的技术支持。
2、您可以在协议规定的约束和限制范围内修改Yuncart源代码或界面风格以适应您的网站要求，但不可以公开对外发布。
3、您拥有使用本软件构建的网站全部内容所有权，并独立承担与这些内容的相关法律义务。
4、未经商业授权，不得将本软件用于商业用途(企业网站或以盈利为目的经营性网站)，否则我们将保留追究的权力。

三、免责声明
1、本软件及所附带的文件是作为不提供任何明确的或隐含的赔偿或担保的形式提供的。
2、用户出于自愿而使用本软件，您必须了解使用本软件的风险，任何情况下，程序的质量风险和性能风险完全由您承担。有可能证实该程序存在漏洞，您需要估算与承担所有必需服务，恢复，修正，甚至崩溃所产生的代价！在尚未购买产品技术服务之前，我们不承诺对免费用户提供任何形式的技术支持、使用担保，也不承担任何因使用本软件而产生问题的相关责任。
3、请务必仔细阅读本授权协议，在您同意授权协议的全部条件后，即可继续Yuncart的安装。即：您一旦开始安装Yuncart，即被视为完全同意本授权协议的全部内容，如果出现纠纷，我们将根据相关法律和协议条款追究责任。

							</textarea>	
							<div class="btn">
								<p>
									<input type="checkbox" value="1" name="copyright" id="copyright" autocomplete="off" checked/>
									<em>我已看过并同意安装许可协议</em>
								</p>
								<p>
									<input type="submit" class="long-button" value="下一步" id="next"/>
								</p>
							</div>
					</div>	
			</form>
			<script type="text/javascript">
			$("#copyright").click(function(){
				$("#next").prop("disabled",!$(this).prop("checked"));
			});
			</script>
		<?php } elseif($step == 2) {  ?>
			<form action="index.php?step=3" method="post" name="submitform" id="submitform" onsubmit="return checkform()">
				<div class="install">
						<h1>系统配置</h1>
						<?php if($errors) { ?>
						<div class="installerr">
							<ul>
							<?php foreach($errors as $error) { ?>
								<li><?php  echo $error ?></li>
							<?php  } ?>
							</ul>
						</div>
						<?php  } else { ?>
						<div class="installipt">
							<p>
								<label><em>*</em>数据库驱动：</label>
								<select name="driver" class="left">
									<option value="mysql">mysql</option>
									<option value="pdo">pdo</option>
								</select>
							</p>
							<p>
								<label><em>*</em>数据库主机：</label>
								<input class="short-input2" name="dbhost" type="text" value="localhost" />
								<span class="left" style="margin-left:5px;">安装有问题？<a href="http://help.yuncart.com" target="_blank">请查看文档</a></span>
							</p>
							<p>
								<label><em>*</em>数据库端口号：</label>
								<input class="short-input2" name="dbport" type="text" value="3306"	/> 
							</p>
							<p>
								<label><em>*</em>数据库用户名：</label>
								<input class="short-input2" name="dbuser" type="text" id="dbuser"	onfocus="focusipt(this)"/>
							</p>
							<p>
								<label><em>*</em>数据库密码：</label>
								<input class="short-input2" name="dbpass" type="text" id="dbpass"	onfocus="focusipt(this)"/> 
							</p>
							<p>
								<label><em>*</em>数据库名：</label>
								<input class="short-input2" name="dbname" type="text" id="dbname"	onfocus="focusipt(this)"/> 
							</p>
							<p>
								<label><em>*</em>数据库表前缀：</label>
								<input class="short-input2" name="dbprefix" type="text" value="cart_"/> 
							</p>
						</div>

						<div class="installipt">
							<p>
								<label><em>*</em>商城名称：</label>
								<input class="short-input2" name="mallname" type="text" id="mallname" onfocus="focusipt(this)" value="我的网店"/>
							</p>
							<p>
								<label><em>*</em>管理员帐号：</label>
								<input class="short-input2" name="adminname" type="text" id="adminname" onfocus="focusipt(this)"/>
							</p>
							<p>
								<label><em>*</em>管理员密码：</label>
								<input class="short-input2" name="adminpass" type="text" id="adminpass" onfocus="focusipt(this)"/> 
							</p>
							<p>
								<label><em>*</em>确认密码：</label>
								<input class="short-input2" name="adminpass2" type="text" id="adminpass2" onfocus="focusipt(this)"/>
							</p>
							<p>
								<label><em>*</em>Email：</label>
								<input class="short-input2" name="email" type="text" id="email" onfocus="focusipt(this)" value="admin@admin.com"/>
							</p>
							<p>
								<label><em>*</em>后台入口：</label>
								<input class="short-input2" name="adminfile" type="text" id="adminfile" onfocus="focusipt(this)" value="admin"
									onkeyup="checkkey()"
								/>
								<span class="left">（安全考虑，请修改并牢记，格式只允许英文，数字，区分大小写）</span>
							</p>
							<p>
								<label>后台地址：</label>
								<span class="left" id="adminloc"></span>
							</p>
							<p>
								<label><em>*</em>安装体验数据：</label>
								<input type="checkbox" value="1" name="test" checked class="short-check"/>
							</p>
						</div>
						<div class="btn">
							<input type="submit" value="填完了，下一步" id="next" />
						</div>
						<?php } ?>
				</div>
			</form>
			<script type="text/javascript">
				function checkkey() {
					var value = $("#adminfile").val();
					if(!/^[0-9a-zA-Z]+$/.test(value)) {
						value = value.replace(/[^0-9a-zA-Z]/g,'');
						$("#adminfile").val(value);
					}
					$("#adminloc").text(value + ".php");
				}
				
				function checkform() {
					var $obj = $("#dbuser");
					if($.trim($obj.val()) == "") {
						$obj.addClass("border");
						return false;
					}
					
					$obj = $("#dbpass");
					if($.trim($obj.val()) == ""){
						$obj.addClass("border");
						return false;
					}

					$obj = $("#dbname");
					if($.trim($obj.val()) == ""){
						$obj.addClass("border");
						return false;
					}
					
					$obj = $("#mallname");
					if($.trim($obj.val()) == ""){
						$obj.addClass("border");
						return false;
					}

					$obj = $("#adminname");
					if($.trim($obj.val()) == ""){
						$obj.addClass("border");
						return false;
					}
					
					$obj = $("#adminpass");
					if($obj.val().length <2){
						$obj.addClass("border");
						return false;
					}

					$obj2 = $("#adminpass2");
					if($obj.val() != $obj2.val()){
						$obj2.addClass("border");
						return false;
					}
					
					$obj = $("#adminfile");
					var adminfile = $obj.val(); 
					if($.trim(adminfile) == "" || !/^[a-zA-Z0-9]+$/.test(adminfile)){
						$obj.addClass("border");
						return false;
					}

					$obj = $("#email");
					if($obj.val() == ""){
						$obj.addClass("border");
						return false;
					}
					return true;
				}	
				function focusipt(obj) {
					var $obj = $(obj);
					$obj.removeClass("border");
				}
			</script>
		<?php } elseif($step == 3) {  ?>
			<div class="install">
				<h1>安装</h1>
				<div class="installipt">
			<?php if($insret) { ?>
					<p class="psucc">安装成功
						<input type="button" value="删除安装目录" id="delins"/>
					</p>
					<p class="pview">
						<a href="../<?php echo $adminfile; ?>.php" target="_blank">访问后台</a>
						<a href="../index.php" target="_blank">访问前台</a>
					</p>
					<script type="text/javascript">
						$("#delins").click(function(){
							var $this = $(this);
							$this.prop("disabled",true);
							$.post("index.php?step=4",{},function(data) {
								if(data == "success") {
									$this.val("删除安装目录成功");
								} else {
									alert("删除安装目录失败，请检查目录权限");
									$this.prop("disabled",false);
								}
							})
						});
						</script>
			<?php } else { ?>
					<p class="perr"><?php  echo $errors ?></p>
			<?php } ?>
				</div>
			</div>
		<?php } else {  ?>
			<div class="install">
				<h1>安装提示</h1>
				<div class="installipt">
				<?php  if($errors) { ?>
					<p class="perr"><?php  echo $errors ?></p>
				<?php  } ?>
				</div>
			</div>
		<?php } ?>
</div>
<div class="footer">Copyright © 2012 版权所有 Powered By <a href="http://www.yuncart.com" target="_blank">yuncart</a></div>
</body>
</html>


