<?php
namespace yii\ftp;

use Yii;

class Ftp
{
	private static $config_host;
	private static $config_port;
	private static $config_user;
	private static $config_password;
	private static $config_is_ssl;
	private static $config_timeout;
	private static $config_pasv;
	private static $config_dir;
	private static $conn_id;
	
	static public function config()
	{
		self::$config_host = FTP_HOST;
		self::$config_user = FTP_USER;
		self::$config_password =  FTP_PASSWORD;
		self::$config_port = 21;
		self::$config_is_ssl = false;// 是否为SSL连接
		self::$config_timeout = 120;// 超时时间
		self::$config_pasv = true;// 是否为主动模式连接
		self::$config_dir = '/';// 上传文件到FTP的哪个目录
	}
	
	//FTP上传
	static function ftpupload($source, $dest) 
	{
		if(!(self::$conn_id = self::sftp_connect())) 
		{
			return 0;
		} 
		else 
		{
			$ftpconnid = self::$conn_id;
		}
		$tmp = explode('/', $dest);
		$dest = array_pop($tmp);

		foreach ($tmp as $tmpdir) 
		{
			if(!self::sftp_chdir($ftpconnid, $tmpdir)) 
			{
				if(!self::sftp_mkdir($ftpconnid, $tmpdir)) 
				{
					self::runlog("MKDIR '$tmpdir' ERROR.");
					return 0;
				}
				if(!function_exists('ftp_chmod') || !self::sftp_chmod($ftpconnid, 0777, $tmpdir)) 
				{
					self::sftp_site($ftpconnid, "'CHMOD 0777 $tmpdir'");
				}
				if(!self::sftp_chdir($ftpconnid, $tmpdir)) 
				{
					self::runlog("CHDIR '$tmpdir' ERROR.");
					return 0;
				}
			}
		}

		if(self::sftp_put($ftpconnid, $dest, $source, FTP_BINARY)) 
		{
			if(file_exists($source.'.thumb.jpg')) 
			{
				if(self::sftp_put($ftpconnid, $dest.'.thumb.jpg', $source.'.thumb.jpg', FTP_BINARY)) 
				{
					@unlink($source);
					@unlink($source.'.thumb.jpg');
					self::sftp_close($ftpconnid);
					return 1;
				} 
				else 
				{
					self::sftp_delete($ftpconnid, $dest);
				}
			} 
			else 
			{
				@unlink($source);
				self::sftp_close($ftpconnid);
				return 1;
			}
		}
		self::runlog("Upload '$source' To '$dest' error.");
		return 0;
	}

	//FTP连接
	static function sftp_connect() 
	{
		@set_time_limit(0);
		
		// 配置
		self::config();

		// 选择函数
		$func = self::$config_is_ssl && function_exists('ftp_ssl_connect') ? 'ftp_ssl_connect' : 'ftp_connect';
		if($func == 'ftp_connect' && !function_exists('ftp_connect')) 
		{
			self::runlog("FTP NOT SUPPORTED.");
		}
		if($ftpconnid = @$func(self::$config_host, self::$config_port, 20)) 
		{
			if(self::$config_timeout && function_exists('ftp_set_option')) 
			{
				@ftp_set_option($ftpconnid, FTP_TIMEOUT_SEC, self::$config_timeout);
			}
			if(self::sftp_login($ftpconnid, self::$config_user, self::$config_password)) 
			{
				if(self::$config_pasv) 
				{
					self::sftp_pasv($ftpconnid, TRUE);
				}
				if(self::sftp_chdir($ftpconnid, self::$config_dir)) 
				{
					return $ftpconnid;
				} 
				else 
				{
					self::runlog("CHDIR '".self::$config_dir."' ERROR.");
				}
			} 
			else 
			{
				self::runlog('530 NOT LOGGED IN.');
			}
		} 
		else 
		{
			self::runlog("COULDN'T CONNECT TO ".self::$config_host.":".self::$config_port);
		}
		self::sftp_close($ftpconnid);
		return -1;
	}
	//FTP远程删除
	static function ftpdelete($path) 
	{
		if(!(self::$conn_id = self::sftp_connect())) 
		{
			return 0;
		} 
		else 
		{
			$ftpconnid = self::$conn_id;
		}
		self::sftp_delete($ftpconnid, $path);
	}
	static function sftp_mkdir($ftp_stream, $directory) 
	{
		$directory = self::wipespecial($directory);
		return @ftp_mkdir($ftp_stream, $directory);
	}

	static function sftp_rmdir($ftp_stream, $directory) 
	{
		$directory = self::wipespecial($directory);
		return @ftp_rmdir($ftp_stream, $directory);
	}

	static function sftp_put($ftp_stream, $remote_file, $local_file, $mode, $startpos = 0 ) 
	{
		$remote_file = self::wipespecial($remote_file);
		$local_file = self::wipespecial($local_file);
		$mode = intval($mode);
		$startpos = intval($startpos);
		return @ftp_put($ftp_stream, $remote_file, $local_file, $mode, $startpos);
	}

	static function sftp_size($ftp_stream, $remote_file) 
	{
		$remote_file = self::wipespecial($remote_file);
		return @ftp_size($ftp_stream, $remote_file);
	}

	static function sftp_close($ftp_stream) 
	{
		return @ftp_close($ftp_stream);
	}

	static function sftp_delete($ftp_stream, $path) 
	{
		$path = self::wipespecial($path);
		return @ftp_delete($ftp_stream, $path);
	}

	static function sftp_get($ftp_stream, $local_file, $remote_file, $mode, $resumepos = 0) 
	{
		$remote_file = self::wipespecial($remote_file);
		$local_file = self::wipespecial($local_file);
		$mode = intval($mode);
		$resumepos = intval($resumepos);
		return @ftp_get($ftp_stream, $local_file, $remote_file, $mode, $resumepos);
	}

	static function sftp_login($ftp_stream, $username, $password) 
	{
		$username = self::wipespecial($username);
		$password = str_replace(array("\n", "\r"), array('', ''), $password);
		return @ftp_login($ftp_stream, $username, $password);
	}

	static function sftp_pasv($ftp_stream, $pasv) 
	{
		$pasv = intval($pasv);
		return @ftp_pasv($ftp_stream, $pasv);
	}

	static function sftp_chdir($ftp_stream, $directory) 
	{
		$directory = self::wipespecial($directory);
		return @ftp_chdir($ftp_stream, $directory);
	}

	static function sftp_site($ftp_stream, $cmd) 
	{
		$cmd = self::wipespecial($cmd);
		return @ftp_site($ftp_stream, $cmd);
	}

	static function sftp_chmod($ftp_stream, $mode, $filename) 
	{
		$mode = intval($mode);
		$filename = self::wipespecial($filename);
		if(function_exists('ftp_chmod')) 
		{
			return @ftp_chmod($ftp_stream, $mode, $filename);
		} 
		else 
		{
			return self::sftp_site($ftp_stream, 'CHMOD '.$mode.' '.$filename);
		}
	}

	static function wipespecial($str) 
	{
		return str_replace(array('..', "\n", "\r"), array('', '', ''), $str);
	}
	
	static function runlog($msg)
	{
		echo $msg;
	}

	static function get_zip_originalsize($filename, $path)
    {
         //先判断待解压的文件是否存在
    	$filename = $path.$filename;
         if(!file_exists($filename)){
          die("文件 $filename 不存在！");
         } 
         $starttime = explode(' ',microtime()); //解压开始的时间
         //将文件名和路径转成windows系统默认的gb2312编码，否则将会读取不到
         $filename = iconv("utf-8","gb2312",$filename);
         $path = iconv("utf-8","gb2312",$path);
         //打开压缩包
         $resource = zip_open($filename);
         $i = 1;
         //遍历读取压缩包里面的一个个文件
         while ($dir_resource = zip_read($resource)) {
          //如果能打开则继续
          if (zip_entry_open($resource,$dir_resource)) {
           //获取当前项目的名称,即压缩包里面当前对应的文件名
           $file_name = str_replace('.zip', '',$filename).'/'.zip_entry_name($dir_resource);
           //以最后一个“/”分割,再用字符串截取出路径部分
           $file_path = substr($file_name,0,strrpos($file_name, "/"));
           //如果路径不存在，则创建一个目录，true表示可以创建多级目录
           if(!is_dir($file_path)){
            mkdir($file_path,0777,true);
           }
           //如果不是目录，则写入文件
           if(!is_dir($file_name)){
            //读取这个文件
            $file_size = zip_entry_filesize($dir_resource);
            //最大读取6M，如果文件过大，跳过解压，继续下一个
            if($file_size<(1024*1024*6)){
             $file_content = zip_entry_read($dir_resource,$file_size);
             // var_dump($file_name);echo '<br>';
             file_put_contents($file_name,$file_content);
            }else{
             echo "<p> ".$i++." 此文件已被跳过，原因：文件过大， -> ".iconv("gb2312","utf-8",$file_name)." </p>";
            }
           }
           //关闭当前
           zip_entry_close($dir_resource);
          }
         }
         //关闭压缩包
         zip_close($resource); 
         $endtime = explode(' ',microtime()); //解压结束的时间
         $thistime = $endtime[0]+$endtime[1]-($starttime[0]+$starttime[1]);
         $thistime = round($thistime,3); //保留3为小数
         echo "<p>解压完毕！，本次解压花费：$thistime 秒。</p>";
    }












}









