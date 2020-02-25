# swoole web-socket 通讯框架

* 替换php-fpm 
* 以swoole 优化php性能
* 实现完整socket 连接消息推送功能

## 缺陷

- 未实现rpc通讯
- 未优化文件下载 导入 等处理 

- 通过nginx转发解决
    server {
        listen     ;
        server_name ;	
        set $root_path '';
        root $root_path; 
        index  index.php index.html index.htm; 
		
	
        location @rewrite {   
            rewrite ^/(.*)$ /index.php?_url=/$1;   
            fastcgi_pass   127.0.0.1:9000;
            fastcgi_index  index.php;
            fastcgi_split_path_info  ^(.+\.php)(/.+)$; 
            fastcgi_param  SCRIPT_FILENAME  $root_path$fastcgi_script_name;
            fastcgi_param  PATH_INFO  $fastcgi_path_info;
            fastcgi_param  PATH_TRANSLATED  $root_path$fastcgi_path_info;
            fastcgi_param  QUERY_STRING     $query_string;
            fastcgi_param  REQUEST_METHOD   $request_method;
            fastcgi_param  CONTENT_TYPE     $content_type;
            fastcgi_param  CONTENT_LENGTH   $content_length;
            include        fastcgi_params;
            break;
        }   

        location = /index.php {  
            try_files /not_exists @swoole;
        }
                
        location / {
            try_files $uri $uri/ @swoole;
        }
          

        location @swoole {
            set $suffix "";
            set $deargs "";
            if ($uri = /index.php) {
                set $suffix "/";
                set $deargs $is_args$args;
            }                  
            proxy_set_header Host $host;
            proxy_set_header SERVER_PORT $server_port;
            proxy_set_header REMOTE_ADDR $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

           
            # IF https
            # proxy_set_header HTTPS "on";
            proxy_pass http://$suffix$deargs;
        }
		
        location ~ .*\.(php|php5) {
            fastcgi_pass   127.0.0.1:9000;
            fastcgi_index  index.php;
            fastcgi_split_path_info  ^(.+\.php)(/.+)$; 
            fastcgi_param  SCRIPT_FILENAME  $root_path$fastcgi_script_name;
            fastcgi_param  PATH_INFO  $fastcgi_path_info;
            fastcgi_param  PATH_TRANSLATED  $root_path$fastcgi_path_info;
            fastcgi_param  QUERY_STRING     $query_string;
            fastcgi_param  REQUEST_METHOD   $request_method;
            fastcgi_param  CONTENT_TYPE     $content_type;
            fastcgi_param  CONTENT_LENGTH   $content_length;
            include        fastcgi_params;
        }
					
       location ~ .*/(input|out|export).* {
             try_files $uri $uri/ @rewrite;
        }
    
        location ~* \.(txt|doc|pdf|rar|gz|zip|docx|exe|xlsx|ppt|pptx|xls|gif|jpg|jpeg|png|bmp|swf)$ {
           try_files $uri $uri/ @rewrite;
        }

    	location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$ {
    		expires 30d;
    	}

	   location ~ .*\.(js|css)?$ {
		expires 1h;
        }

        access_log  /alidata/server/nginx/logs/htms_v2_center.log;

        }


- ob异常抛出解决 
    例如：
        ob_start();
        // Download
        $this->writer->save('php://output');
        $string = ob_get_clean();
        <!-- ob_flush(); -->
        throw new \App\Tars\exceptions\ExcelException($string,[
                'Content-Type'        => $this->contentType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '.' . $this->ext . '"',
                'Expires'             => 'Mon, 26 Jul 1997 05:00:00 GMT', // Date in the past
                'Last-Modified'       => Carbon::now()->format('D, d M Y H:i:s'),
                'Cache-Control'       => 'cache, must-revalidate',
                'Pragma'              => 'public'
    ]);