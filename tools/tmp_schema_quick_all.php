<?php
require __DIR__ . '/../config/db.php';
$tables=['clientes_morales','clientes_fideicomisos','clientes_nacionalidades'];
foreach($tables as $t){
 echo "---{$t}---\n";
 $st=$pdo->query("SHOW COLUMNS FROM `{$t}`");
 foreach($st as $c){$d=$c['Default']; if($d===null)$d='NULL'; echo $c['Field'].'|'.$c['Null'].'|'.$d."\n";}
}
