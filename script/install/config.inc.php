<?php

/* Servers configuration */
$i = 0;

/* Server: localhost [1] */
$i++;
$cfg['Servers'][$i]['verbose'] = '';
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['port'] = '';
$cfg['Servers'][$i]['socket'] = '';
$cfg['Servers'][$i]['connect_type'] = 'tcp';
$cfg['Servers'][$i]['extension'] = 'mysqli';
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['hide_db'] = '(mysql|information_schema|performance_schema|phpmyadmin)';

/* End of servers configuration */

$cfg['DefaultLang'] = 'en-utf-8';
$cfg['blowfish_secret'] = '(~sbz(q#W3g9%fCPL%~ELp}=|*tG3Ng[OMJRBD53H';
$cfg['ServerDefault'] = 1;
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';

?>