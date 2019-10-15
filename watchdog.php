<?php
    date_default_timezone_set('America/Bogota');
    require_once './.config.php';

    class WatchDog {
        public $monitoreado_dir = '';
        public $origen_dir = '';

        public $alertar = false;
        public $comandos = '';

        function __construct($monitoreado_dir, $origen_dir) {
            $this->monitoreado_dir = $monitoreado_dir;
            $this->origen_dir = $origen_dir;
        }

        public function comparar() {
            $monitoreado_dir_content = $this->exploreDir($this->monitoreado_dir);
            $origen_dir_content = $this->exploreDir($this->origen_dir);
            foreach($monitoreado_dir_content as $filename_monitoreado) {
                $existe = false;
                foreach($origen_dir_content as $filename_origen) { 
                    if ($filename_origen['basename'] == $filename_monitoreado['basename']) {
                        $existe = true;
                        if ($existe) {
                            $md5_origen = $this->getChecksumFromFile($filename_origen['filename']);
                            $md5_monitoreado = $this->getChecksumFromFile($filename_monitoreado['filename']);
                            $igual = false;
                            if($md5_monitoreado == $md5_origen) {
                                $igual = true;
                            }
                            if (!$igual) {
                                $this->copiar($filename_origen['filename'], $this->monitoreado_dir, $this->origen_dir);
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
            foreach($origen_dir_content as $filename_origen) {
                $existe = false;
                foreach($monitoreado_dir_content as $filename_monitoreado) {
                    if ($filename_origen['basename'] == $filename_monitoreado['basename']) {
                        $existe = true;
                    }
                }
                if (!$existe) {
                    $this->copiar($filename_origen['filename'], $this->monitoreado_dir, $this->origen_dir);
                    $this->alertar = true;
                }
            }
            if ($this->alertar) {
                $this->notificar();
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
            $this->comandos .= $comando . "\n";
            $resultado = shell_exec($comando);
        }

        protected function notificar() {
            $log = fopen('./log/log_' . date("Y_m_d_H_i_s") .'.log', "w");
            $content = "Archivos restaurados.\n\n" . $this->comandos;
            fwrite($log, $content);
            fclose($log);
            $information = ["thisYear"=>date("Y"),
                            "para"=>"TICS",
                            "server"=>CONFIG['SERVER'],
                           ];
            $data = ["tipoMail"=>"ataque",
                     "email"=>CONFIG['MAIL_TO_NOTIFY'],
                     "subject"=>"Ataque detectado ". date("Y-m-d H:i.s"),
                     "information"=>$information,
                    ];             
            $this->httpPost('http://ws-siturin-mailer.turismo.gob.ec/enviar', json_encode($data));
            $this->comandos = '';
            $this->alertar = false;
        }

        protected function exploreDir($directory) {
            $fileList = glob($directory . '/*');
            $contenido_base = [];
            $totalContent = [];
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
    
    $watchDog = new WatchDog(CONFIG['TARGET_DIR'], CONFIG['SOURCE_DIR']);
    
    while(true) {
        $watchDog->comparar();
        sleep(10);
    }
