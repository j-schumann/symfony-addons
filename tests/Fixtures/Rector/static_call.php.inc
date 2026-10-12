<?php

MyClass::staticMethod(['x' => 1, 'y' => 2]);
OtherClass::staticMethod(['x' => 1]);

?>
-----
<?php

MyClass::staticMethod(x: 1, y: 2);
OtherClass::staticMethod(['x' => 1]);

?>
