<?php
    date_default_timezone_set('America/Bogota');
    require_once './.config.php';

    class WatchDog {
        public $monitoreado_dir = '';
        public $cambios = '';

        function __construct($monitoreado_dir) {
            $this->monitoreado_dir = $monitoreado_dir;
        }

        public function comparar() {
            $resultado = shell_exec('./git_compare.h ' . $this->monitoreado_dir);
            $found = strpos($resultado, "nothing to commit, working directory clean");
            if (!$found) {
               $this->cambios = $resultado;
               $this->reparar();
               $this->notificar();
               return false;
            } else {
               $this->cambios = '';
               return true;
            }
        }

        protected function reparar() {
            $reparado = shell_exec('./git_repare.h ' . $this->monitoreado_dir);
        }

        protected function notificar() {
            $log = fopen('./log/log_' . date("Y_m_d_H_i_s") .'.log', "w");
            $content = "CAMBIOS\n\n" . $this->cambios;
            fwrite($log, $content);
            fclose($log);
            $config = CONFIG;
            $server = $config['SERVER'];
            $information = ["thisYear"=>date("Y"),
                            "para"=>"TICS",
                            "server"=>$server,
                            "log"=>base64_encode($this->cambios)
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
    $watchDog = new WatchDog($setup['TARGET_DIR']);

    while(true) {
        $watchDog->comparar();
        sleep(10);
    }
