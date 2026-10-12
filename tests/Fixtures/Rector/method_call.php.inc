<?php

class MyClass
{
    public function instanceMethod(int $p, int $q): void
    {
    }
}

class MyChild extends MyClass
{
}

class OtherClass
{
    public function instanceMethod(array $options): void
    {
    }
}

function run(MyChild $obj, OtherClass $other, $unknown): void
{
    $obj->instanceMethod(['p' => 1, 'q' => 2]);
    $other->instanceMethod(['p' => 1]);
    $unknown->instanceMethod(['p' => 1]);
}

?>
-----
<?php

class MyClass
{
    public function instanceMethod(int $p, int $q): void
    {
    }
}

class MyChild extends MyClass
{
}

class OtherClass
{
    public function instanceMethod(array $options): void
    {
    }
}

function run(MyChild $obj, OtherClass $other, $unknown): void
{
    $obj->instanceMethod(p: 1, q: 2);
    $other->instanceMethod(['p' => 1]);
    $unknown->instanceMethod(['p' => 1]);
}

?>
