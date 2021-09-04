<?php 

function getUserMetaQuery($user_id, $key, $as = ""){
  if($as == ""){
    $as = $key;
  }
  $sql = "(select m.val from single_input m where m.entity = 'user' and m.entity_id = $user_id and m.key_input = '$key') as $as";  
  return $sql;
}

?>