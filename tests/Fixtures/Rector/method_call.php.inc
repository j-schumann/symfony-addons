<?php

function run($obj): void
{
    $obj->instanceMethod(['p' => 1, 'q' => 2]);
    $obj->otherMethod(['p' => 1]);
}

?>
-----
<?php

function run($obj): void
{
    $obj->instanceMethod(p: 1, q: 2);
    $obj->otherMethod(['p' => 1]);
}

?>
