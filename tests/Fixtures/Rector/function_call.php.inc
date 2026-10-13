<?php

foo(['a' => 1, 'b' => [1, 2]]);
bar(['a' => 1]);
foo(['a' => 1], 2);
foo([1, 2]);
foo($options);
foo([]);
foo([1 => 'a', 'b' => 2]);
$fn(['a' => 1]);

?>
-----
<?php

foo(a: 1, b: [1, 2]);
bar(['a' => 1]);
foo(['a' => 1], 2);
foo([1, 2]);
foo($options);
foo([]);
foo([1 => 'a', 'b' => 2]);
$fn(['a' => 1]);

?>
