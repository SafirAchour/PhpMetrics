<?php
namespace Hal\Metric\Class_;

use Hal\Component\Reflected\Method;
use Hal\Metric\ClassMetric;
use Hal\Metric\FunctionMetric;
use Hal\Metric\Helper\RoleOfMethodDetector;
use Hal\Metric\InterfaceMetric;
use Hal\Metric\Metrics;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

class ClassEnumVisitor extends NodeVisitorAbstract
{

    /**
     * @var Metrics
     */
    private $metrics;

    /**
     * ClassEnumVisitor constructor.
     * @param Metrics $metrics
     */
    public function __construct(Metrics $metrics)
    {
        $this->metrics = $metrics;
    }


    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\Class_
            || $node instanceof Stmt\Interface_
        ) {

            if ($node instanceof Stmt\Interface_) {
                $class = new InterfaceMetric($node->namespacedName->toString());
                $class->set('interface', true);
            } else {

                if(null === $node->name) {
                    // anonymous class
                    $name = 'anonymous@' . spl_object_hash($node);
                } else {
                    $name = $node->namespacedName->toString();
                }

                $class = new ClassMetric($name);
                $class->set('interface', false);
            }

            $methods = [];

            $methodsPublic = $methodsPrivate = $nbGetters = $nbSetters = 0;
            $roleDetector = new RoleOfMethodDetector();
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Stmt\ClassMethod) {

                    $function = new FunctionMetric($stmt->name);

                    $role = $roleDetector->detects($stmt);
                    $function->set('role', $role);
                    switch ($role) {
                        case 'getter':
                            $nbGetters++;
                            break;
                        case 'setter':
                            $nbSetters++;
                            break;
                    }

                    if (null === $role) {
                        if ($stmt->isPublic()) {
                            $methodsPublic++;
                            $function->set('public', true);
                            $function->set('private', false);
                        }

                        if ($stmt->isPrivate() || $stmt->isProtected()) {
                            $methodsPrivate++;
                            $function->set('public', false);
                            $function->set('private', true);
                        }
                    }

                    array_push($methods, $function);
                }
            }

            $class->set('methods', $methods);
            $class->set('nbMethodsIncludingGettersSetters', sizeof($methods) );
            $class->set('nbMethods', sizeof($methods) - ($nbGetters + $nbSetters));
            $class->set('nbMethodsPrivate', $methodsPrivate);
            $class->set('nbMethodsPublic', $methodsPublic);
            $class->set('nbMethodsGetter', $nbGetters);
            $class->set('nbMethodsSetters', $nbSetters);

            $this->metrics->attach($class);
        }
    }
}