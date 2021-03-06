<?php

#http://localhost.promo/
#http://localhost.promo/?unidade=diadema
#http://localhost.promo/?unidade=diadema&div=1
#http://requirejs.org/docs/start.html
require_once 'Autoloader.php';
require_once 'api/library/PHPMailer-master/send.php';
require_once 'Helper.php';
$db = new Conn("localhost", "promo_cadastro", "root", "");

//$latitude = $xml->result->geometry->location->lat;

/**
 * Instanciamento de das classes
 */
$cliente       = new Cliente();
$divulgador    = new Divulgador();
$unidade       = new Unidade();
$curso         = new Curso();
$profissao     = new Profissao();
$template      = new Template();
$mail          = new SendMail();
$helper        = new Helper();
$geo           = new Geolocalizacao();
$objCliente    = new ServiceCliente   ($db,   $cliente   );
$objDivulgador = new ServiceDivulgador($db,   $divulgador);
$objUnidade    = new ServiceUnidade   ($db,   $unidade   );
$objCurso      = new ServiceCurso     ($db,   $curso     );
$objProfissao  = new ServiceProfissao ($db,   $profissao );
$objMail       = new ServiceSendMail  ($mail, $sndmail, $template);

if (isset($_GET['unidade'])) {
    # Condi��o de tratamento do REQUEST divulgador
    $div = @$_GET['div'] != ''         ? $_GET['div']         : 0;
    $div = isset($_POST['divulgador']) ? $_POST['divulgador'] : $div;

    # Retorna p�gina em branco
    $divulgador->setId($div);
    $inputDivulgador = $objDivulgador->select();
    $unidade->setAlias($helper->sanitizeString($_GET['unidade']));
    $inputUnidade = $objUnidade->findByAlias();

    # Variaveis para montar o endereco
    $end_page['telefone'] = $inputUnidade[0]['telefone'];
    $end_page['cep'     ] = $inputUnidade[0]['cep'     ];
    $end_page['endereco'] = $inputUnidade[0]['endereco'];
    $end_page['bairro'  ] = $inputUnidade[0]['bairro'  ];
    $end_page['cidade'  ] = $inputUnidade[0]['cidade'  ];
    $end_page['estado'  ] = $inputUnidade[0]['estado'  ];

    # Pega a latitude
    $geo->setEndereco(utf8_decode("{$end_page['endereco']} - {$end_page['bairro']} - {$end_page['estado']}"));
    $geo->setLocalizacao();
    $end_page['latitude' ] = $geo->getLatitude();
    $end_page['longitude'] = $geo->getLongitude();
    $end_page['geo_key'  ] = $geo->key;

    # Caso o formul�rio tenha sido executado
    if (isset($_GET['action'])) {
        if ($_POST) {
            $nome           = isset($_POST['nome'          ]) ? $_POST['nome'          ] : NULL;
            $email          = isset($_POST['email'         ]) ? $_POST['email'         ] : NULL;
            $telefone       = isset($_POST['telefone'      ]) ? $_POST['telefone'      ] : NULL;
            $curso          = isset($_POST['curso'         ]) ? $_POST['curso'         ] : NULL;
            $especializacao = isset($_POST['especializacao']) ? $_POST['especializacao'] : NULL;
            $profissao      = isset($_POST['profissao'     ]) ? $_POST['profissao'     ] : NULL;
            $periodo        = isset($_POST['periodo'       ]) ? $_POST['periodo'       ] : NULL;
            $unidade        = isset($_POST['unidade'       ]) && is_numeric($_POST['unidade']) ? $_POST['unidade'] : NULL;
            $divulgador_id  = isset($_POST['divulgador'    ]) && is_numeric($_POST['divulgador']) ? $_POST['divulgador'] : 0;
            $strCurso       = "<option value=\"\">Escolha um curso</option>";
            foreach ($objCurso->show('padrao') as $value):
                $select = ($value['id'] == $curso) ? " selected=\"selected\"" : "";
                $strCurso .= "<option value=\"{$value['id']}\"{$select}>" . utf8_encode($value['nome']) . "</option>";
            endforeach;
            $strEspecializacao = "<option value=\"\">Escolha uma especializa��o</option>";
            foreach ($objCurso->show('especializacao') as $value):
                $select = ($value['id'] == $especializacao) ? " selected=\"selected\"" : "";
                $strEspecializacao .= "<option value=\"{$value['id']}\"{$select}>" . utf8_encode($value['nome']) . "</option>";
            endforeach;
            $strProfissao = "<option value=\"\">Escolha uma profiss�o</option>";
            foreach ($objProfissao->show() as $value):
                $select = ($value['id'] == $profissao) ? " selected=\"selected\"" : "";
                $strProfissao .= "<option value=\"{$value['id']}\"{$select}>" . utf8_encode($value['nome']) . "</option>";
            endforeach;

            # Confere se todos os campos foram preenchidos
            $arrError = array();
            if ($nome == NULL) { $arrError[] = "Preencha o campo <strong>nome</strong><br/>"; }
            if ($email == NULL) {
                $arrError[] = "Preencha o campo <strong>e-mail</strong><br/>";
            } else if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
                $arrError[] = "Insira um <strong>e-mail</strong> v�lido!<br/>";
            } else if (count($objCliente->findEmail($email)) > 0) {
                $arrError[] = "O e-mail <strong>{$email}</strong> j� foi cadastrado!<br/>";
            }
            if ($telefone       == NULL) { $arrError[] = "Preencha o campo <strong>telefone</strong><br/>";   }
            if ($curso          == NULL) { $arrError[] = "Selecione um <strong>curso</strong><br/>";          }
            if ($especializacao == NULL) { $arrError[] = "Selecione um <strong>especializa��o</strong><br/>"; }
            if ($profissao      == NULL) { $arrError[] = "Selecione uma <strong>profiss�o</strong><br/>";     }
            if ($periodo        == NULL) { $arrError[] = "Escolha um <strong>per�odo</strong><br/>";          }

            # Se ocorreu algum erro
            if (count($arrError) > 0) {
                $strMsg = NULL;
                foreach ($arrError as $value):
                    $strMsg .=$value;
                endforeach;
                $page = $template->getCadastro();
                $page = str_replace("%ERROR-MSG%",             "<h3>Erro no preenchimento do formul�rio!</h3>" . $strMsg, $page);
                $page = str_replace("%UNIDADE-ALIAS%",         $inputUnidade[0]['alias'], $page                                );
                $page = str_replace("%UNIDADE-ID%",            $inputUnidade[0]['id'   ], $page                                );
                $page = str_replace("%DIVULGADOR-ID%",         $divulgador_id,            $page                                );
                $page = str_replace("%NOME%",                  $nome,                     $page                                );
                $page = str_replace("%EMAIL%",                 $email,                    $page                                );
                $page = str_replace("%TELEFONE%",              $telefone,                 $page                                );
                $page = str_replace("%CURSO-SELECT%",          $strCurso,                 $page                                );
                $page = str_replace("%ESPECIALIZACAO-SELECT%", $strEspecializacao,        $page                                );
                $page = str_replace("%PROFISSAO-SELECT%",      $strProfissao,             $page                                );
                $page = str_replace("%CHECKED-M%",             ($periodo == "M") ? 'checked="checked"' : "", $page             );
                $page = str_replace("%CHECKED-T%",             ($periodo == "T") ? 'checked="checked"' : "", $page             );
                $page = str_replace("%CHECKED-N%",             ($periodo == "N") ? 'checked="checked"' : "", $page             );
                echo $page;
            } else {
                # Ent�o salva o cadastro
                $cadDate = date('Y-m-d');
                $valDate = date('Y-m-d', strtotime($cadDate . ' + 7 days'));
                $cliente->setNome            ( utf8_decode($nome) )
                        ->setEmail           ( $email          )
                        ->setTelefone        ( $telefone       )
                        ->setUnidadeId       ( $unidade        )
                        ->setDivulgadorId    ( $divulgador_id  )
                        ->setCursoId         ( $curso          )
                        ->setEspecializacaoId( $especializacao )
                        ->setProfissaoId     ( $profissao      )
                        ->setPeriodo         ( $periodo        )
                        ->setDate            ( $cadDate        )
                        ->setVencimento      ( $valDate        );
                $clienteId = $objCliente->save();

                # Insere o voucher no cadastro
                $codVoucher = $helper->voucherCodigo($clienteId, $inputUnidade[0]['sigla']);
                $codVoucher = isset($codVoucher) ? $codVoucher : $inputUnidade[0]['sigla'] . "-ND" . $clienteId;
                $cliente->setId($clienteId)
                        ->setCodVoucher($codVoucher);
                $objCliente->update();

                # Seta e dispara e-mails
                $rstCurso = $objCurso->find($curso);
                $rstEspecializacao = $objCurso->find($especializacao);
                $rstProfissao = $objProfissao->find($profissao);
                $msgContent = array();
                $msgContent['divulgador_email'] = count($inputDivulgador) > 0 ? $inputDivulgador[0]['email'] : NULL;
                $msgContent['endereco'        ] = $end_page;
                $msgContent['nome'            ] = $nome;
                $msgContent['email'           ] = $email;
                $msgContent['telefone'        ] = $telefone;
                $msgContent['periodo'         ] = $periodo;
                $msgContent['curso'           ] = $rstCurso[0]['nome'];
                $msgContent['especializacao'  ] = $rstEspecializacao[0]['nome'];
                $msgContent['profissao'       ] = $rstProfissao[0]['nome'];
                $msgContent['voucher'         ] = $codVoucher;
                $msgContent['validade'        ] = $helper->dataBr($valDate);
                #$mail->setMailTo($inputUnidade[0]['email']                ) # envio p/ unidade
                $mail->setMailTo("sdcomputadores@gmail.com"                ) # envo p/ teste
                        ->setMailFrom("adriano.costa@grupolaunic.com.br"   ) # remetente  
                        ->setMailCc($email                                 ) # cop. cliente # destinat�rio
                        ->setMailBcc("php.sql5@gmail.com"                  ) # cop. oculta
                        ->setMailSubject("Cadastro Voucher: " . $codVoucher)
                        ->setMailMsg($msgContent);
                $objMail->send();
                $voucherPage = $template->getVoucher();
                $voucherPage = str_replace("%NOME%",           $nome,                             $voucherPage);
                $voucherPage = str_replace("%CODIGO-VOUCHER%", $codVoucher,                       $voucherPage);
                $voucherPage = str_replace("%LONGITUDE%",      $end_page['longitude'],            $voucherPage);
                $voucherPage = str_replace("%LATITUDE%",       $end_page['latitude' ],            $voucherPage);
                $voucherPage = str_replace("%CURSO%",          utf8_encode($rstCurso[0]['nome']), $voucherPage);

                # Para o Designer remover em produ��o
                # Begin
                # Retorna a confirma��o de envio
                echo $voucherPage;
                echo "<pre>";
                print_r($end_page);
                echo "</pre>";
                # End
            }
        }
    } else { # Ent�o retorna o formul�rio limpo
        $strCurso = "<option value=\"\">Escolha um curso</option>";
        foreach ($objCurso->show('padrao') as $curso):
            $strCurso .= "<option value=\"{$curso['id']}\">" . utf8_encode($curso['nome']) . "</option>";
        endforeach;
        $strEspecializacao = "<option value=\"\">Escolha um curso</option>";
        foreach ($objCurso->show('especializacao') as $curso):
            $strEspecializacao .= "<option value=\"{$curso['id']}\">" . utf8_encode($curso['nome']) . "</option>";
        endforeach;
        $strProfissao = "<option value=\"\">Escolha uma profiss�o</option>";
        foreach ($objProfissao->show() as $value):
            $strProfissao .= "<option value=\"{$value['id']}\">" . utf8_encode($value['nome']) . "</option>";
        endforeach;
        $page = $template->getCadastro();
        $page = str_replace("%ERROR-MSG%",             "",                        $page);
        $page = str_replace("%UNIDADE-ALIAS%",         $inputUnidade[0]['alias'], $page);
        $page = str_replace("%UNIDADE-ID%",            $inputUnidade[0]['id'],    $page);
        $page = str_replace("%NOME%",                  "",                        $page);
        $page = str_replace("%EMAIL%",                 "",                        $page);
        $page = str_replace("%TELEFONE%",              "",                        $page);
        $page = str_replace("%CURSO-SELECT%",          $strCurso,                 $page);
        $page = str_replace("%ESPECIALIZACAO-SELECT%", $strEspecializacao,        $page);
        $page = str_replace("%PROFISSAO-SELECT%",      $strProfissao,             $page);
        $page = str_replace("%CHECKED-M%",             'checked="checked"',       $page);
        $page = str_replace("%CHECKED-T%",             "",                        $page);
        $page = str_replace("%CHECKED-N%",             "",                        $page);
        $page = str_replace("%DIVULGADOR-ID%",         isset($inputDivulgador[0]['id']) ? $inputDivulgador[0]['id'] : 0, $page);
        echo $page;
    }
}else {
    echo "<h1>Erro:</h1>";
    echo "<h3>Nenhuma unidade foi informada!</h3>";
    /*
      echo "<p>Por favor, selecione uma unidade da lista abaixo:</p>";
      foreach ($objUnidade->show() as $unidade):
      echo "<a href=\"?unidade={$unidade['alias']}\">" . utf8_encode($unidade['nome']) . "</a><br/>";
      endforeach;
     */
}
?>