<?php

declare(strict_types=1);

namespace Rector\Core\PhpParser\Node\Manipulator;

use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Type\Type;
use Rector\Core\PhpParser\Node\NodeFactory;
use Rector\NodeNameResolver\NodeNameResolver;

final class ClassInsertManipulator
{
    /**
     * @var NodeNameResolver
     */
    private $nodeNameResolver;

    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    public function __construct(NodeFactory $nodeFactory, NodeNameResolver $nodeNameResolver)
    {
        $this->nodeNameResolver = $nodeNameResolver;
        $this->nodeFactory = $nodeFactory;
    }

    /**
     * @param ClassMethod|Property|ClassConst|ClassMethod $stmt
     */
    public function addAsFirstMethod(Class_ $class, Stmt $stmt): void
    {
        if ($this->tryInsertBeforeFirstMethod($class, $stmt)) {
            return;
        }
        if ($this->tryInsertAfterLastProperty($class, $stmt)) {
            return;
        }

        $class->stmts[] = $stmt;
    }

    public function addConstantToClass(Class_ $class, string $constantName, ClassConst $classConst): void
    {
        if ($this->hasClassConstant($class, $constantName)) {
            return;
        }

        $this->addAsFirstMethod($class, $classConst);
    }

    /**
     * @param Property[] $properties
     */
    public function addPropertiesToClass(Class_ $class, array $properties): void
    {
        foreach ($properties as $property) {
            $this->addAsFirstMethod($class, $property);
        }
    }

    public function addPropertyToClass(Class_ $class, string $name, ?Type $type): void
    {
        if ($this->hasClassProperty($class, $name)) {
            return;
        }

        $property = $this->nodeFactory->createPrivatePropertyFromNameAndType($name, $type);
        $this->addAsFirstMethod($class, $property);
    }

    public function addInjectPropertyToClass(Class_ $class, string $name, ?Type $type): void
    {
        if ($this->hasClassProperty($class, $name)) {
            return;
        }

        $propertyNode = $this->nodeFactory->createPublicInjectPropertyFromNameAndType($name, $type);
        $this->addAsFirstMethod($class, $propertyNode);
    }

    public function addAsFirstTrait(Class_ $class, string $traitName): void
    {
        $traitUse = new TraitUse([new FullyQualified($traitName)]);

        $this->addStatementToClassBeforeTypes(
            $class,
            $traitUse,
            [TraitUse::class, Property::class, ClassMethod::class]
        );
    }

    /**
     * @param Stmt[] $nodes
     * @return Stmt[]
     */
    public function insertBefore(array $nodes, Stmt $stmt, int $key): array
    {
        array_splice($nodes, $key, 0, [$stmt]);

        return $nodes;
    }

    private function tryInsertBeforeFirstMethod(Class_ $class, Stmt $stmt): bool
    {
        foreach ($class->stmts as $key => $classStmt) {
            if (! $classStmt instanceof ClassMethod) {
                continue;
            }

            $class->stmts = $this->insertBefore($class->stmts, $stmt, $key);
            return true;
        }

        return false;
    }

    private function tryInsertAfterLastProperty(Class_ $class, Stmt $stmt): bool
    {
        $previousElement = null;
        foreach ($class->stmts as $key => $classStmt) {
            if ($previousElement instanceof Property && ! $classStmt instanceof Property) {
                $class->stmts = $this->insertBefore($class->stmts, $stmt, $key);

                return true;
            }

            $previousElement = $classStmt;
        }

        return false;
    }

    private function hasClassConstant(Class_ $class, string $constantName): bool
    {
        foreach ($class->getConstants() as $classConst) {
            if ($this->nodeNameResolver->isName($classConst, $constantName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Waits on https://github.com/nikic/PHP-Parser/pull/646
     */
    private function hasClassProperty(Class_ $class, string $name): bool
    {
        foreach ($class->getProperties() as $property) {
            if (! $this->nodeNameResolver->isName($property, $name)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param string[] $types
     */
    private function addStatementToClassBeforeTypes(Class_ $class, Stmt $stmt, array $types): void
    {
        foreach ($types as $type) {
            foreach ($class->stmts as $key => $classStmt) {
                if (! $classStmt instanceof $type) {
                    continue;
                }

                $class->stmts = $this->insertBefore($class->stmts, $stmt, $key);

                return;
            }
        }

        $class->stmts[] = $stmt;
    }
}
