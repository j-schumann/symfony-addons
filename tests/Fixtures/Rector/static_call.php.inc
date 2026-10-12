<?php

class MyClass
{
    public static function staticMethod(int $x, int $y): void
    {
    }

    public function run(): void
    {
        self::staticMethod(['x' => 1, 'y' => 2]);
        static::staticMethod(['x' => 1, 'y' => 2]);
    }
}

class MyChild extends MyClass
{
}

class OtherClass
{
    public static function staticMethod(array $options): void
    {
    }
}

MyClass::staticMethod(['x' => 1, 'y' => 2]);
MyChild::staticMethod(['x' => 1, 'y' => 2]);
OtherClass::staticMethod(['x' => 1]);

?>
-----
<?php

class MyClass
{
    public static function staticMethod(int $x, int $y): void
    {
    }

    public function run(): void
    {
        self::staticMethod(x: 1, y: 2);
        static::staticMethod(x: 1, y: 2);
    }
}

class MyChild extends MyClass
{
}

class OtherClass
{
    public static function staticMethod(array $options): void
    {
    }
}

MyClass::staticMethod(x: 1, y: 2);
MyChild::staticMethod(x: 1, y: 2);
OtherClass::staticMethod(['x' => 1]);

?>
