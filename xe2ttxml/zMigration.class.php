<?php
    /**
     * @brief XE용을 개조하여 TTXML로 출력하도록 만든 파일
     * @author venister@empal.com (메일을 보내도 잘 안봄)
     **/
		
    class zMigration {

		// #####################################################################################
		// 다음을 고치세요.
		// #####################################################################################
		var $tistory_id = " "; // TISTORY 백업에서 구한 본인의 티스토리 아이디의 일련번호
		var $tistory_name = " "; // 티스토리 닉네임
		var $tistory_passwd = " ";  // TISTORY 백업에서 얻어낸 패스워드 해시값
		var $xe_admin_id = "V_L";	// XE에서 관리자 ID. 자기의 ID를 기입하시면 됩니다.
		
		// #####################################################################################
		// 아래는 고치지 마세요.
		// #####################################################################################

        var $connect;
        var $handler;

        var $errno = 0;
        var $error = null;

        var $path = null;
        var $module_type = 'member';
        var $module_id = '';

        var $filename = '';

        var $item_count = 0;

        var $source_charset = 'EUC-KR';
        var $target_charset = 'UTF-8';

        var $db_info = null;
		var $attaches = null;

        function zMigration() {
        }

        function setPath($path) {
            $this->path = $path;
        }

        function setModuleType($module_type = 'member', $module_id = null) {
            $this->module_type = $module_type;
            if($this->module_type == 'module') $this->module_id = $module_id;
        }

        function setCharset($source_charset = 'EUC-KR', $target_charset = 'UTF-8') {
            $this->source_charset = $source_charset;
            $this->target_charset = $target_charset;
        }

        function setDBInfo($db_info) {
            $this->db_info = $db_info;
        }

        function setItemCount($count) {
            $this->item_count = $count;
        }

        function setFilename($filename) {
            $this->filename = $filename;
        }

        function dbConnect() {
            switch($this->db_info->db_type) {
                case 'mysql' :
                case 'mysql_innodb' :
                        if (strpos($this->db_info->db_hostname, ':') === false && $this->db_info->db_port)
                            $this->db_info->db_hostname .= ':' . $this->db_info->db_port;
                        $this->connect =  @mysql_connect($this->db_info->db_hostname, $this->db_info->db_userid, $this->db_info->db_password);
                        if(!mysql_error()) @mysql_select_db($this->db_info->db_database, $this->connect);
                        if(mysql_error()) return mysql_error();
                        if($this->source_charset == 'UTF-8') mysql_query("set names 'utf8'");
                    break;

                case 'mysqli' :
                case 'mysqli_innodb' :
                        $this->connect =  mysqli_connect($this->db_info->db_hostname, $this->db_info->db_userid, $this->db_info->db_password,$this->db_info->db_database,$this->db_info->db_port);
                        if(mysql_error()) return mysqli_error();
                        if($this->source_charset == 'UTF-8') mysqli_query($this->connect, "set names 'utf8'");
                    break;
                case 'cubrid' :
                        $this->connect = @cubrid_connect($this->db_info->db_hostname, $this->db_info->db_port, $this->db_info->db_database, $this->db_info->db_userid, $this->db_info->db_password);
                        if(!$this->connect) return 'database connect fail';
                    break;
                case 'sqlite3_pdo' :
                        if(substr($this->db_info->db_database,0,1)!='/') $this->db_info->db_database = $this->path.'/'.$this->db_info->db_database;
                        if(!file_exists($this->db_info->db_database)) return "database file not found";
                        $this->handler = new PDO('sqlite:'.$this->db_info->db_database);
                        if(!file_exists($this->db_info->db_database) || $error) return 'permission denied to access database';
                    break;
                case 'sqlite' :
                        if(substr($this->db_info->db_database,0,1)!='/') $this->db_info->db_database = $this->path.'/'.$this->db_info->db_database;
                        if(!file_exists($this->db_info->db_database)) return "database file not found";
                        $this->connect = @sqlite_open($this->db_info->db_database, 0666, $error);
                        if($error) return $error;
                    break;
            }
        }

        function dbClose() {
            if(!$this->connect) return;
            mysql_close($this->connect);
        }

        function getLimitQuery($start, $limit_count) {
            switch($this->db_info->db_type) {
                case 'postgresql' :
                        return sprintf(" offset %d limit %d ", $start, $limit_count);
                case 'cubrid' :
                        return sprintf(" for ordeby_num() between %d and %d ", $start, $limit_count);
                default :
                        return sprintf(" limit %d, %d ", $start, $limit_count);
                    break;
            }
        }

        function query($query) {
            switch($this->db_info->db_type) {
                case 'mysql' :
                case 'mysql_innodb' :
                        return mysql_query($query);
                    break;
                case 'mysqli' :
                case 'mysqli_innodb' :
                        return mysqli_query($this->connect, $query);
                    break;
                case 'cubrid' :
                        return @cubrid_execute($this->connect, $query);
                    break;
                case 'sqlite3_pdo' :
                        $stmt = $this->handler->prepare($query);
                        $stmt->execute();
                        return $stmt;
                    break;
                case 'sqlite' :
                        return sqlite_query($query, $this->connect);
                    break;
            }
        }

        function fetch($result) {
            switch($this->db_info->db_type) {
                case 'mysql' :
                case 'mysql_innodb' :
                        return mysql_fetch_object($result);
                    break;
                case 'mysqli' :
                case 'mysqli_innodb' :
                        return mysqli_fetch_object($result);
                    break;
                case 'cubrid' :
                        return cubrid_fetch($result, CUBRID_OBJECT);
                    break;
                case 'sqlite3_pdo' :
                        $tmp = $result->fetch(2);
                        if($tmp) {
                            foreach($tmp as $key => $val) {
                                $pos = strpos($key, '.');
                                if($pos) $key = substr($key, $pos+1);
                                $obj->{$key} = str_replace("''","'",$val);
                            }
                        }
                        return $obj;
                    break;
                case 'sqlite' :
                        $tmp = sqlite_fetch_array($result, SQLITE_ASSOC);
                        unset($obj);
                        if($tmp) {
                            foreach($tmp as $key => $val) {
                                $pos = strpos($key, '.');
                                if($pos) $key = substr($key, $pos+1);
                                $obj->{$key} = $val;
                            }
                        }
                        return $obj;
                    break;
            }
        }

		//CHANGED!
		// 새로 추가된 함수. Slogan을 문자열로부터 추출한다.
		function getSlogan($slogan) {
			$slogan = preg_replace('/-+/', ' ', $slogan);
			$slogan = preg_replace('@[!-/:-\@\[-\^`{-~]+@', '', $slogan);
			$slogan = preg_replace('/\s+/', '-', $slogan);
			$slogan = trim($slogan, '-');
			return strlen($slogan) > 0 ? $slogan : 'XFile';
		}
		
        function printHeader() {
            if(!$this->filename) {
                if($this->module_type == 'member') $filename = 'member.xml';
                elseif($this->module_type == 'message') $filename = 'message.xml';
                else $filename = sprintf("%s.xml", $this->module_id);
            } else $filename = $this->filename;

            if(strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
                $filename = urlencode($filename);
                $filename = preg_replace('/\./', '%2e', $filename, substr_count($filename, '.') - 1);
            }

            header("Content-Type: application/octet-stream");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header("Content-Transfer-Encoding: binary");

            printf('<?xml version="1.0" encoding="utf-8" ?>%s',"\r\n");

            if($this->module_type == 'member'){
				printf('<members count="%d" pubDate="%s">%s', $this->item_count, date("YmdHis"), "\r\n");
			} else if($this->module_type == 'message'){
				printf('<messages count="%d" pubDate="%s">%s', $this->item_count, date("YmdHis"), "\r\n");
			} else {
				//CHANGED!
				//TTXML로 변환
				print "<blog type=\"tattertools/1.1\" migrational=\"true\">\r\n";
			}
        }

        function printFooter() { 
            if($this->module_type == 'member') print('</members>');
            elseif($this->module_type == 'message') print('</messages>');
            else print('</blog>'); //CHANGED!
        }

        function printString($string) {
			//CHANGED!
			print htmlspecialchars(stripslashes($string));
        }
		
		function getString($string) {
			return htmlspecialchars(stripslashes($string));
        }

        function printBinary($filename) {
            $filesize = filesize($filename);
            if($filesize<1) return;

            $fp = fopen($filename,"r");
            if($fp) {
                while(!feof($fp)) {
                    $buff .= fgets($fp, 1024);
                    if(strlen($buff)>1024*512) {
                        print base64_encode($buff);
                        $buff = null;
                    }
                }
                if($buff) print base64_encode($buff);
                fclose($fp);
            }
            return null;
        }

        function printMemberItem($obj) {
            // member 태그 시작
            print "<member>\r\n";

            // extra_vars를 제외한 변수 출력
            foreach($obj as $key => $val) {
                if($key == 'extra_vars' || !$val) continue;

                if($key == 'image_nickname' || $key == 'image_mark' || $key == 'profile_image') {
                    if(file_exists($val)) {
                        printf("<%s>", $key);
                        $this->printBinary($val);
                        printf("</%s>\r\n", $key);
                    }
                    continue;
                }

                printf("<%s>", $key); $this->printString($val); printf("</%s>\r\n", $key);
            }

            if(count($obj->extra_vars)) {
                print("<extra_vars>\r\n");
                foreach($obj->extra_vars as $key => $val) {
                    if(!$val) continue;
                    printf("<%s>", $key); $this->printString($val); printf("</%s>\r\n", $key);
                } 
                print("</extra_vars>\r\n");
            }

            // member 태그 닫음
            print "</member>\r\n";
        }

        function printMessageItem($obj) {
            // member 태그 시작
            print "<message>\r\n";

            foreach($obj as $key => $val) {
                printf("<%s>", $key); $this->printString($val); printf("</%s>\r\n", $key);
            }

            // member 태그 닫음
            print "</message>\r\n";
        }


		//CHANGED: TTXML은 Categories라는 것이 없음. XE는 '전체'를 별도의 카테고리화 시키지 않으므로 전체를 자동으로 만들어줌
		//         나는 트리형 카테고리를 쓰지 않으므로 카테고리는 그냥 1차원에서 끝. (귀찮...)
        function printCategoryItem($obj) {
            if(!count($obj)) return;
			$i = 1;

            foreach($obj as $key => $val) {
				print "<category><name>";
                $this->printString($val->title);
                printf("</name><priority>%d</priority></category>\r\n", $i);
				$i++;
            }
			print "<category><name>전체</name><priority>1</priority><root>1</root></category>";
        }
		
		// 컴포넌트때문에 추가 시킨 코드.
		function imageTagHandler($matches){
			//attribute 검색
			preg_match_all('!(?:([\w\d_]+)\s*=\s*("[^"\\\\]*(?:[^"\\\\]*)*"|\'[^\'\\\\]*(?:[^\'\\\\]*)*\'|[^\s]+))!is', $matches[0], $tmp, PREG_SET_ORDER);
			
			//img 태그의 각 attribute를 사용하기 편리하게 분리시켜준다.
			foreach($tmp as $tmp2){
				if((strpos($tmp2[2], "\"") == 0) or (strpos($tmp2[2], "'") == 0)) $tmp2[2] = substr($tmp2[2], 1, strlen($tmp2[2]) - 2);
				$attr[$tmp2[1]] = $tmp2[2];
			}
			
			if(array_key_exists($attr[src], $this->attaches)){
				if($this->attaches[$attr[src]]->isImage){
					$ret = sprintf("[##_1C|%s|width=\"%d\" height=\"%d\" alt=\"%s\" filename=\"%s\" filemime=\"%s\"|_##]", $this->attaches[$attr[src]]->tt_name, $this->attaches[$attr[src]]->width, $this->attaches[$attr[src]]->height, htmlspecialchars($attr[alt]), $this->attaches[$attr[src]]->filename, $this->attaches[$attr[src]]->mime_type);
				} else {
					$ret = sprintf("[##_1C|%s|filename=\"%s\" filemime=\"%s\"|_##]", $this->attaches[$attr[src]]->tt_name, $this->attaches[$attr[src]]->filename, $this->attaches[$attr[src]]->mime_type);
				}
				return $ret;
			} else {
				return $matches;
			}
		}
		
		function printContent($content) {
			//$content 컨버팅
			if(count($this->attaches) > 0) $content = preg_replace_callback('/<img[^>]+>/i', array($this,'imageTagHandler'), $content);
			
			//br태그 정리
			$content = str_ireplace("<br>", "<br />", $content);
			$content = str_ireplace("<br/>", "<br />", $content);
			
			//$content 프린팅
			printf("\r\n<content>%s</content>", $this->getString($content));
		}

        function printPostItem($sequence, $obj, $exclude_attach = 'N') {
            unset($obj->extra_vars);			// 안쓸꺼 지워버림. 의미있나?
            $trackbacks = $obj->trackbacks;		// 트랙백. 사실 의미없음.
            unset($obj->trackbacks);
            $comments = $obj->comments;			// 댓글. 역시 의미없음.
            unset($obj->comments);

			unset($this->attaches);
			// 우선 첨부파일에 대한 목록을 만든다.
			if(count($obj->attaches) > 0) foreach($obj->attaches as $key => $val) if(file_exists($val->file)) $this->attaches[$val->filename] = new ttAttachment($val);
			unset($obj->attaches);
			
			// 포스트 헤더 출력
			printf("\r\n<post slogan=\"%s\" format=\"1.1\">", $this->getSlogan($this->getString($obj->title)));

			// 포스트 출력(작성자/제목/내용)
			printf("<author domain=\"tistory\">%s</author>", $this->tistory_id);
			printf("<id>%d</id><visibility>public</visibility><title>%s</title>", $sequence, $this->getString($obj->title));
			
			// 내용 출력(가장 중요)
			$this->printContent($obj->content);

			// 잡다구리한 포스트 관련 데이터 출력
			printf("<location>/</location><password>%s</password><acceptComment>1</acceptComment><acceptTrackback>1</acceptTrackback><published>%s</published><created>%s</created><modified>%s</modified><category>%s</category>", $this->tistory_passwd, ztime($obj->regdate), ztime($obj->regdate), ztime($obj->update), $obj->category);
			
			// 태그 출력
			if($obj->tags){
				foreach(explode(",", $obj->tags) as $tag){
					printf("<tag>%s</tag>", $this->getString($tag));
				}
			}

            // 엮인글 출력
            if(count($trackbacks) > 0) {
                foreach($trackbacks as $key => $val) {
					printf("<trackback><url>%s</url><site>%s</site><title>%s</title><excerpt>%s</excerpt><ip>%s</ip><received>%s</received><isFiltered>0</isFiltered></trackback>", $val->url, $val->blog_name, $val->title, $this->getString($val->excerpt), $val->ip_address, ztime($val->regdate));
                }
            }
			
			// 첨부파일 출력
			if(count($this->attaches) > 0){
				foreach($this->attaches as $key => $val){
					printf("\r\n<attachment mime=\"%s\" size=\"%d\" width=\"%d\" height=\"%d\"><name>%s</name><label>%s</label><enclosure>0</enclosure><attached>%s</attached><downloads>%d</downloads>", $val->mime_type, $val->size, $val->width, $val->height, $val->tt_name, $val->filename, ztime($obj->regdate), $val->download_count);
					// 졸라 핵폭탄 -_-
					if($exclude_attach != "Y"){
						print "<content>"; $this->printBinary($val->file); print "</content>";
					}
					print "</attachment>";
				}
			}
			
            // 댓글 출력
            $comment_count = count($comments);
            if($comment_count) {
                foreach($comments as $key => $val) {
                    unset($val->attaches);

					if($val->user_id && $val->user_id == $this->xe_admin_id){
						$commenter_id = $val->user_id;
						$commenter_name = $this->tistory_name;
						$passwd = "";
					} else {
						$commenter_id = "";
						if($val->nick_name) $commenter_name = $this->getString($val->nick_name); else $commenter_name = "방문자";
						$passwd = $val->password;
					}
					
					$commenter_homepage = ($val->homepage)?$this->getString($val->homepage):"";
					$comment_content = $this->getString(strip_tags(br2nl($val->content)));

					printf("\r\n<comment><commenter id=\"%s\"><name>%s</name><homepage>%s</homepage><ip>%s</ip></commenter><content>%s</content><password>%s</password><written>%s</written><isFiltered>0</isFiltered></comment>", $commenter_id, $commenter_name, $commenter_homepage, $val->ipaddress, $comment_content, $val->password, ztime($val->regdate));
                }
            }

            print "</post>";
        }

        // xe에서 경로 설정시 사용되는 함수
        function getNumberingPath($no, $size=3) {
            $mod = pow(10,$size);
            $output = sprintf('%0'.$size.'d/', $no%$mod);
            if($no >= $mod) $output .= $this->getNumberingPath((int)$no/$mod, $size);
            return $output;
        }

        // 첨부파일의 절대경로를 구함
        function getFileUrl($file) {
            $doc_root = $_SERVER['DOCUMENT_ROOT'];
            $file = str_replace($doc_root, '', realpath($file));
            if(substr($file,0,1)==1) $file = substr($file,1);
            return 'http://'.$_SERVER['HTTP_HOST'].'/'.$file;
        }
    }
	
	function ztime($str) {
        if(!$str) return;
        $hour = (int)substr($str,8,2);
        $min = (int)substr($str,10,2);
        $sec = (int)substr($str,12,2);
        $year = (int)substr($str,0,4);
        $month = (int)substr($str,4,2);
        $day = (int)substr($str,6,2);
		
        //if(strlen($str) <= 8) {
            $gap = 0;
        //} else {
		//  $gap = zgap();
        //}

        return mktime($hour, $min, $sec, $month?$month:1, $day?$day:1, $year)+$gap;
    }
	
	function br2nl($str)
	{
		eregi_replace("(<br>|<br \/>)","\n",$str);
		return $str;
	}
	
	class ttAttachment {
		var $tt_name = "";
		var $mime_type = "";
		var $filename = "";
		var $file = "";
		var $download_count = 0;
		var $width = 0;
		var $height = 0;
		var $ext = "";
		var $isImage = false;
		var $size = 0;

		function getMimeType($ext = '') {
			$mimes = array(
			  'hqx'  =>  'application/mac-binhex40',
			  'cpt'   =>  'application/mac-compactpro',
			  'doc'   =>  'application/msword',
			  'bin'   =>  'application/macbinary',
			  'dms'   =>  'application/octet-stream',
			  'lha'   =>  'application/octet-stream',
			  'lzh'   =>  'application/octet-stream',
			  'exe'   =>  'application/octet-stream',
			  'class' =>  'application/octet-stream',
			  'psd'   =>  'application/octet-stream',
			  'so'    =>  'application/octet-stream',
			  'sea'   =>  'application/octet-stream',
			  'dll'   =>  'application/octet-stream',
			  'oda'   =>  'application/oda',
			  'pdf'   =>  'application/pdf',
			  'ai'    =>  'application/postscript',
			  'eps'   =>  'application/postscript',
			  'ps'    =>  'application/postscript',
			  'smi'   =>  'application/smil',
			  'smil'  =>  'application/smil',
			  'mif'   =>  'application/vnd.mif',
			  'xls'   =>  'application/vnd.ms-excel',
			  'ppt'   =>  'application/vnd.ms-powerpoint',
			  'wbxml' =>  'application/vnd.wap.wbxml',
			  'wmlc'  =>  'application/vnd.wap.wmlc',
			  'dcr'   =>  'application/x-director',
			  'dir'   =>  'application/x-director',
			  'dxr'   =>  'application/x-director',
			  'dvi'   =>  'application/x-dvi',
			  'gtar'  =>  'application/x-gtar',
			  'php'   =>  'application/x-httpd-php',
			  'php4'  =>  'application/x-httpd-php',
			  'php3'  =>  'application/x-httpd-php',
			  'phtml' =>  'application/x-httpd-php',
			  'phps'  =>  'application/x-httpd-php-source',
			  'js'    =>  'application/x-javascript',
			  'swf'   =>  'application/x-shockwave-flash',
			  'sit'   =>  'application/x-stuffit',
			  'tar'   =>  'application/x-tar',
			  'tgz'   =>  'application/x-tar',
			  'xhtml' =>  'application/xhtml+xml',
			  'xht'   =>  'application/xhtml+xml',
			  'zip'   =>  'application/zip',
			  'mid'   =>  'audio/midi',
			  'midi'  =>  'audio/midi',
			  'mpga'  =>  'audio/mpeg',
			  'mp2'   =>  'audio/mpeg',
			  'mp3'   =>  'audio/mpeg',
			  'aif'   =>  'audio/x-aiff',
			  'aiff'  =>  'audio/x-aiff',
			  'aifc'  =>  'audio/x-aiff',
			  'ram'   =>  'audio/x-pn-realaudio',
			  'rm'    =>  'audio/x-pn-realaudio',
			  'rpm'   =>  'audio/x-pn-realaudio-plugin',
			  'ra'    =>  'audio/x-realaudio',
			  'rv'    =>  'video/vnd.rn-realvideo',
			  'wav'   =>  'audio/x-wav',
			  'bmp'   =>  'image/bmp',
			  'gif'   =>  'image/gif',
			  'jpeg'  =>  'image/jpeg',
			  'jpg'   =>  'image/jpeg',
			  'jpe'   =>  'image/jpeg',
			  'png'   =>  'image/png',
			  'tiff'  =>  'image/tiff',
			  'tif'   =>  'image/tiff',
			  'css'   =>  'text/css',
			  'html'  =>  'text/html',
			  'htm'   =>  'text/html',
			  'shtml' =>  'text/html',
			  'txt'   =>  'text/plain',
			  'text'  =>  'text/plain',
			  'log'   =>  'text/plain',
			  'rtx'   =>  'text/richtext',
			  'rtf'   =>  'text/rtf',
			  'xml'   =>  'text/xml',
			  'xsl'   =>  'text/xml',
			  'mpeg'  =>  'video/mpeg',
			  'mpg'   =>  'video/mpeg',
			  'mpe'   =>  'video/mpeg',
			  'qt'    =>  'video/quicktime',
			  'mov'   =>  'video/quicktime',
			  'avi'   =>  'video/x-msvideo',
			  'movie' =>  'video/x-sgi-movie',
			  'doc'   =>  'application/msword',
			  'word'  =>  'application/msword',
			  'xl'    =>  'application/excel',
			  'eml'   =>  'message/rfc822'
			);
			return ( ! isset($mimes[strtolower($ext)])) ? 'application/x-unknown-content-type' : $mimes[strtolower($ext)];
		}
		
		function getFileExtension($path) {
			for ($i = strlen($path) - 1; $i >= 0; $i--) {
				if ($path{$i} == '.')
					return strtolower(substr($path, $i + 1));
				if (($path{$i} == '/') || ($path{$i} == '\\'))
					break;
			}
			return '';
		}
		
		function ttAttachment($att){
			$this->filename = htmlspecialchars($att->filename);
			$this->file = $att->file;
			$this->download_count = $att->download_count;
			
			// TT용 파일이름
			$this->ext = array_pop(explode(".", strtolower($this->filename)));
			$this->tt_name = rand(1000000000,9999999999).".".$this->ext;
			$this->mime_type = $this->getMimeType($this->ext);
			$this->size = filesize($this->file);
			
			if($this->ext == "jpeg" or $this->ext == "jpg" or $this->ext == "gif" or $this->ext == "png") $this->isImage = true;
			if($this->isImage){
				if($tmp = @getimagesize($this->file)){
					$this->width = $tmp[0];
					$this->height = $tmp[1];
				}
			}
		}
	}
?>
