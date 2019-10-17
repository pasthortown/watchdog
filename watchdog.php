<?php
    date_default_timezone_set('America/Bogota');
    require_once './.config.php';

    class WatchDog {
        public $monitoreado_dir = '';
        public $origen_dir = '';
        private $sonIguales = false;
        public $alertar = false;
        public $comandos = '';
        private $monitoreado_dir_content = [];
        private $origen_dir_content = [];

        function __construct($monitoreado_dir, $origen_dir) {
            $this->monitoreado_dir = $monitoreado_dir;
            $this->origen_dir = $origen_dir;
        }

        public function comparar() {
            $sonIguales = $this->son_iguales();
            $this->monitoreado_vs_origen($this->monitoreado_dir_content, $this->origen_dir_content);
            if ($this->alertar) {
                $this->notificar();
            }
            $this->origen_vs_monitoreado($this->monitoreado_dir_content, $this->origen_dir_content);
            if ($this->alertar) {
                $this->notificar();
            }
            sleep(10);
        }

        protected function origen_vs_monitoreado() {
            $index = 0;
            $this->sonIguales = $this->son_iguales();
            while(!$this->sonIguales) {
                $filename_origen = $this->origen_dir_content[$index];
                $existe = false;
                foreach($this->monitoreado_dir_content as $filename_monitoreado) {
                    if ($filename_origen['basename'] == $filename_monitoreado['basename']) {
                        $existe = true;
                    }
                }
                if (!$existe) {
                    $this->copiar($filename_origen['filename'], $this->monitoreado_dir, $this->origen_dir);
                    $this->alertar = true;
                }
                $this->sonIguales = $this->son_iguales($this->monitoreado_dir_content, $this->origen_dir_content);
                if (!$this->sonIguales) {
                    $index++;
                }
            }
        }

        protected function son_iguales() {
            $toReturn = true;
            $this->monitoreado_dir_content = $this->exploreDir($this->monitoreado_dir);
            $this->origen_dir_content = $this->exploreDir($this->origen_dir);
            foreach($this->origen_dir_content as $origen_file) {
                $existe = false;
                $igual = false;
                foreach($this->monitoreado_dir_content as $monitoreado_file) {
                    $monitoreado_file_filename = $monitoreado_file['filename'];
                    $origen_file_filename = $origen_file['filename'];
                    $monitoreado_file_filename = str_replace($this->monitoreado_dir, $this->origen_dir, $monitoreado_file_filename);
                    if ($monitoreado_file_filename == $origen_file_filename) {
                        $existe = true;
                        $md5_origen = $this->getChecksumFromFile($monitoreado_file['filename']);
                        $md5_target = $this->getChecksumFromFile($origen_file['filename']);
                        $comp_result = strcmp($md5_origen, $md5_target);
                        if ( $comp_result != 0 ) {
                            $igual = true;
                        }   
                    }
                }
                if (!$existe) {
                    $toReturn = false;
                } 
                if (!$igual) {
                    $toReturn = false;
                }
            }
            return $toReturn;
        }

        protected function monitoreado_vs_origen() {
            foreach($this->monitoreado_dir_content as $filename_monitoreado) {
                $existe = false;
                foreach($this->origen_dir_content as $filename_origen) {
                    $file_name_origen = $filename_origen['filename'];
                    $file_name_monitoreado = $filename_monitoreado['filename'];
                    $file_name_monitoreado = str_replace($this->monitoreado_dir, $this->origen_dir, $file_name_monitoreado);
                    if (strcmp($file_name_origen, $file_name_monitoreado) == 0) {
                        $existe = true;
                        if ($existe) {
                            $md5_origen = $this->getChecksumFromFile($filename_origen['filename']);
                            $md5_monitoreado = $this->getChecksumFromFile($filename_monitoreado['filename']);
                            $igual = false;
                            if(strcmp($md5_monitoreado, $md5_origen) == 0) {
                                $igual = true;
                            }
                            if (!$igual) {
                                echo "diferente";
                                //$this->copiar($filename_origen['filename'], $this->monitoreado_dir, $this->origen_dir);
                                $this->alertar = true;
                            }
                        }
                    }
                }
                if (!$existe) {
                    $this->borrar($filename_monitoreado['filename']);
                    $this->alertar = true;
                }
            }
        }

        protected function borrar($filename) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if($ext == '') {
                $comando = 'rm -R ' . $filename;
            } else {
                $comando = 'rm ' . $filename;
            }
            $this->comandos .= $comando . "\n";
            $resultado = shell_exec($comando);
        }

        protected function copiar($filename, $hacia, $origen) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $destino = str_replace($origen, $hacia, $filename);
            if($ext == '') {
                $comando = 'cp -R ' . $filename . ' ' . $destino;
            } else {
                $comando = 'cp ' . $filename . ' ' . $destino;
            }
            $this->alertar = true;
            $this->comandos .= $comando . "\n";
            $resultado = shell_exec($comando);
        }

        protected function notificar() {
            $log = fopen('./log/log_' . date("Y_m_d_H_i_s") .'.log', "w");
            $content = "Archivos restaurados.\n\n" . $this->comandos;
            fwrite($log, $content);
            fclose($log);
            $config = CONFIG;
            $server = $config['SERVER'];
            $information = ["thisYear"=>date("Y"),
                            "para"=>"TICS",
                            "server"=>$server
                           ];
            $email = $config['MAIL_TO_NOTIFY'];
            $data = ["tipoMail"=>"ataque",
                     "email"=>$email,
                     "subject"=>"Ataque detectado ". date("Y-m-d H:i.s"),
                     "information"=>$information
                    ];             
            $this->httpPost('http:
//ws-siturin-mailer.turismo.gob.ec/enviar', json_encode($data));
            $this->comandos = '';
            $this->alertar = false;
        }

        protected function exploreDir($directory) {
            $fileListToExplore = glob($directory . '/*');
            $contenido_base = [];
            $totalContent = [];
            $fileList = [];
            foreach($fileListToExplore as $path) {
                $exclude = $this->is_excluded($path);
                if (!$exclude) {
                    array_push($fileList, $path);
                }
            }
            foreach($fileList as $filename){
                array_push($contenido_base, $filename);
            }
            if (sizeof($contenido_base) > 0) {
                foreach($contenido_base as $item) {
                    $md5 = $this->getChecksumFromFile($item);
                    array_push($totalContent, [
                        "filename"=>$item,
                        "basename"=>basename($item),
                        "md5"=>$md5,
                    ]);
                    $totalContent = array_merge($totalContent, $this->exploreDir($item));
                }
            }
            return $totalContent;
        }
        
        protected function is_excluded($path) {
            $exclude = false;
            $excludes = EXCLUDES;
            foreach($excludes as $dirname) {
                if (basename(dirname($path)) == $dirname) {
                    $exclude = true;
                }
                $path_pieces = explode("/", $path);
                if ($path_pieces[sizeof($path_pieces) - 1] == $dirname) {
                   $exclude = true;
                }
            }
            return $exclude;
        }

        protected function getChecksumFromFile($filename) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if($ext == '') {
                return 0;
            } else {
                return md5_file($filename);
            }
        }

        protected function httpPost($url, $data=NULL, $headers = NULL) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1);
            if(!empty($data)){
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            $headersSend = array('Content-Type: application/json');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headersSend);
            $response = curl_exec($ch);
    
            if (curl_error($ch)) {
                trigger_error('Curl Error:' . curl_error($ch));
            }
            curl_close($ch);
            return $response;
        }
    }
    
    $setup = CONFIG;
    $watchDog = new WatchDog($setup['TARGET_DIR'], $setup['SOURCE_DIR']);
    
    while(true) {
        $watchDog->comparar();
    }
