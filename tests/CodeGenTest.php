<?php

namespace Ray\Aop;

use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Builder\Method;

class CodeGenTest extends \PHPUnit_Framework_TestCase
{
    public function testAddNullDefaultWithAssisted()
    {
        $codeGen = new CodeGen((new ParserFactory)->newInstance(), new BuilderFactory, new Standard);
        $bind = new Bind;
        $bind->bindInterceptors('run', []);
        $code = $codeGen->generate('a', new \ReflectionClass(FakeAssistedConsumer::class), $bind);
        $expected = 'function run($a, $b = null, $c = null)';
        $this->assertContains($expected, $code);
    }

    public function testTypeDeclarations()
    {
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            return;
        }
        $codeGen = new CodeGen((new ParserFactory)->newInstance(), new BuilderFactory, new Standard);
        $bind = new Bind;
        $bind->bindInterceptors('run', []);
        $code = $codeGen->generate('a', new \ReflectionClass(FakePhp7Class::class), $bind);
        if (method_exists(Method::class, 'setReturnType')) {
            $expected = 'function run(string $a, int $b, float $c, bool $d) : array';
        } else {
            $expected = 'function run(string $a, int $b, float $c, bool $d)';
        }
        $this->assertContains($expected, $code);
    }
}
