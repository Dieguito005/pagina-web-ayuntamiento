<?php
$c=ftp_connect('ftpupload.net');
ftp_login($c,'if0_41609770','OllJ5CCGubeoe');
ftp_pasv($c,true);
print_r(ftp_nlist($c, '/'));
