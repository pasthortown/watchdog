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
            if ($this->alertar) {
                $this->notificar();
            }
        }

        protected function son_iguales() {
            $resultado = shell_exec('./git_compare.h ' . $monitoreado_dir);
            echo $resultado;
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
            $this->httpPost('http://ws-siturin-mailer.turismo.gob.ec/enviar', json_encode($data));
            $this->comandos = '';
            $this->alertar = false;
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
        sleep(10);
    }
