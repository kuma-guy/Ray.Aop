<?php
/**
 * This file is part of the Ray.Aop package
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace Ray\Aop;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPParser\Builder\Method;
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;
use Ray\Aop\Annotation\AbstractAssisted;

final class CodeGenMethod
{
    /**
     * @var \PHPParser\Parser
     */
    private $parser;

    /**
     * @var \PHPParser\BuilderFactory
     */
    private $factory;

    /**
     * @var \PHPParser\PrettyPrinter\Standard
     */
    private $printer;

    private $reader;

    /**
     * @var AbstractAssisted
     */
    private $assisted = [];

    /**
     * @param \PHPParser\Parser                 $parser
     * @param \PHPParser\BuilderFactory         $factory
     * @param \PHPParser\PrettyPrinter\Standard $printer
     */
    public function __construct(
        Parser $parser,
        BuilderFactory $factory,
        Standard $printer
    ) {
        $this->parser = $parser;
        $this->factory = $factory;
        $this->printer = $printer;
        $this->reader = new AnnotationReader;
    }

    /**
     * @param \ReflectionClass $class
     *
     * @return array
     */
    public function getMethods(\ReflectionClass $class, BindInterface $bind)
    {
        $bindingMethods = array_keys($bind->getBindings());
        $stmts = [];
        $methods = $class->getMethods();
        foreach ($methods as $method) {
            $this->assisted = $this->reader->getMethodAnnotation($method, AbstractAssisted::class);
            $isBindingMethod = in_array($method->getName(), $bindingMethods);
            /* @var $method \ReflectionMethod */
            if ($isBindingMethod && $method->isPublic()) {
                $stmts[] = $this->getMethod($method);
            }
        }

        return $stmts;
    }

    /**
     * Return method statement
     *
     * @param \ReflectionMethod $method
     *
     * @return \PhpParser\Node\Stmt\ClassMethod
     */
    private function getMethod(\ReflectionMethod $method)
    {
        $methodStmt = $this->factory->method($method->name);
        $params = $method->getParameters();
        foreach ($params as $param) {
            $methodStmt = $this->getMethodStatement($param, $methodStmt);
        }
        $methodInsideStatements = $this->getMethodInsideStatement();
        $methodStmt->addStmts($methodInsideStatements);
        $node = $this->addMethodDocComment($methodStmt, $method);

        return $node;
    }

    /**
     * Return parameter reflection
     *
     * @param \ReflectionParameter      $param
     * @param \PHPParser\Builder\Method $methodStmt
     *
     * @return \PHPParser\Builder\Method
     */
    private function getMethodStatement(\ReflectionParameter $param, Method $methodStmt)
    {
        $isOverPhp7 = version_compare(PHP_VERSION, '7.0.0') >= 0;
        /** @var $paramStmt Param */
        $paramStmt = $this->factory->param($param->name);
        /* @var $param \ReflectionParameter */
        $typeHint = $param->getClass();
        $this->setParameterType($param, $paramStmt, $isOverPhp7, $typeHint);
        $this->setDefault($param, $paramStmt);
        if ($isOverPhp7) {
            $this->setReturnType($param, $methodStmt, $isOverPhp7);
        }
        $methodStmt->addParam($paramStmt);

        return $methodStmt;
    }

    /**
     * @param Method            $methodStmt
     * @param \ReflectionMethod $method
     *
     * @return \PhpParser\Node\Stmt\ClassMethod
     */
    private function addMethodDocComment(Method $methodStmt, \ReflectionMethod $method)
    {
        $node = $methodStmt->getNode();
        $docComment = $method->getDocComment();
        if ($docComment) {
            $node->setAttribute('comments', [new Doc($docComment)]);
        }

        return $node;
    }

    /**
     * @return \PHPParser\Node[]
     */
    private function getMethodInsideStatement()
    {
        $code = file_get_contents(dirname(__DIR__) . '/src-data/CodeGenTemplate.php');
        $node = $this->parser->parse($code)[0];
        /** @var $node \PHPParser\Node\Stmt\Class_ */
        $node = $node->getMethods()[0];

        return $node->stmts;
    }

    /**
     * @param \ReflectionParameter $param
     * @param Param                $paramStmt
     * @param \ReflectionClass     $typeHint
     *
     * @codeCoverageIgnore
     */
    private function setTypeHint(\ReflectionParameter $param, Param $paramStmt, \ReflectionClass $typeHint = null)
    {
        if ($typeHint) {
            $paramStmt->setTypeHint($typeHint->name);
        }
        if ($param->isArray()) {
            $paramStmt->setTypeHint('array');
        }
        if ($param->isCallable()) {
            $paramStmt->setTypeHint('callable');
        }
    }

    /**
     * @param \ReflectionParameter $param
     * @param Param                $paramStmt
     */
    private function setDefault(\ReflectionParameter $param, $paramStmt)
    {
        if ($param->isDefaultValueAvailable()) {
            $paramStmt->setDefault($param->getDefaultValue());

            return;
        }
        if ($this->assisted && in_array($param->getName(), $this->assisted->values)) {
            $paramStmt->setDefault(null);
        }
    }

    /**
     * @param \ReflectionParameter $param
     * @param Param                $paramStmt
     * @param bool                 $isOverPhp7
     * @param \ReflectionClass     $typeHint
     */
    private function setParameterType(\ReflectionParameter $param, Param $paramStmt, $isOverPhp7, \ReflectionClass $typeHint = null)
    {
        if (! $isOverPhp7) {
            $this->setTypeHint($param, $paramStmt, $typeHint); // @codeCoverageIgnore

            return; // @codeCoverageIgnore
        }
        $type = $param->getType();
        if ($type) {
            $paramStmt->setTypeHint((string) $type);
        }
    }

    /**
     * @param \ReflectionParameter $param
     * @param Method $methodStmt
     */
    private function setReturnType(\ReflectionParameter $param, Method $methodStmt)
    {
        $returnType = $param->getDeclaringFunction()->getReturnType();
        if ($returnType && method_exists($methodStmt, 'setReturnType')) {
            $methodStmt->setReturnType((string)$returnType); // @codeCoverageIgnore
        }
    }
}
