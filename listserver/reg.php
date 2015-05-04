<?php
error_reporting(0);
if(!array_key_exists('p',$_GET)) die();
$porto=intval($_GET['p']);
if(!($porto>0&&$porto<=65535)) die();
//将IP端口转化为二进制
$port=pack('v',$porto);
$addrarray=explode('.',$_SERVER['REMOTE_ADDR']);
$addr='';
for($i=3;$i>=0;$i--)
{
	$addr.=pack('C',$addrarray[$i]);
}
$val=$addr.$port;
$needtest=true;
//读取文件
require('settings.php');
$storage=new SaeStorage($access_key,$secret_key);
$file=$storage->read($domain,'servers');
if($file!==false&&strlen($file)%10==2)
{
	for($i=2;$i<strlen($file);$i+=10)
	{
		if(substr($file,$i,6)===$val)
		{
			$needtest=false;
			break;
		}
	}
}
//检查外部端口是否开放
if(array_key_exists('r',$_GET))
{
	if($_GET['r']=='1')
		$needtest=false;
}
if($needtest)
{
	$socket=fsockopen($_SERVER['REMOTE_ADDR'],$porto,$errno,$errstr,10);
	if(!$socket)
		die('1');
	fclose($socket);
}
$version=pack('v',1);
//读取文件，剔除重复和超时的服务器
$newfile='';
$dup=false;
$time=time();
if($file!==false)
{
	$len=strlen($file);
	if($len%10==2)
	{
		$cut=false;
		for($i=2;$i<$len;$i+=10)
		{
			$timestamp=substr($file,$i+6,4);
			$timestamp2=unpack('L',$timestamp);
			$addrport=substr($file,$i,6);
			if($addrport===$val)
				$dup=true;
			if($timestamp2[1]<$time-3600)
			{
				$cut=true;
				break;
			}
			if($dup)
				continue;
			$newfile.=$addrport.$timestamp;
		}
		if($len==202)//如果servers文件已满，那么要处理full文件
		{
			if($cut)
			{
				$storage->delete($domain,'full');
			}
			else if(!$dup)
			{
				$filefull=$storage->read($domain,'full');
				$len=strlen($filefull);
				$newfilefull='';
				if($len%10==2)
				{
					for($i=2;$i<$len;$i+=10)
					{
						$timestamp=substr($filefull,$i+6,4);
						$timestamp2=unpack('L',$timestamp);
						if($timestamp2[1]<$time-3600) break;
						$addrport=substr($filefull,$i,6);
						if($addrport===$val)
						{
							$dup=true;
							continue;
						}
						$newfilefull.=$addrport.$timestamp;
					}
				}
				if(!$needtest&&!$dup) die('2');
				$piece=substr($file,192,10);
				$newfile=substr($file,2,190);
				$newfilefull=$version.$piece.$newfilefull;
				$storage->write($domain,'full',$newfilefull);
			}
		}
	}
}
if(!$needtest&&!$dup) die('2');
//保存文件
$time=pack('L',$time);
$newfile=$version.$val.$time.$newfile;
$b=$storage->write($domain,'servers',$newfile);
if($b===false)
	die('a');
else
	echo '0';
?>