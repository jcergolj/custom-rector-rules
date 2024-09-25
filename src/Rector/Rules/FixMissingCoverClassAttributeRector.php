<?php

namespace Jcergolj\CustomRectorRules\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\Attributes\CoversClass;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class FixMissingCoverClassAttributeRector extends AbstractRector
{
    private const SPECIAL_TEST_NAMES = ['CreateTest', 'UpdateTest', 'DeleteTest', 'ShowTest', 'IndexTest', 'DestroyTest'];

    /**
     * Specify the node types that this rule should process.
     *
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        // We are targeting class declarations
        return [Class_::class];
    }

    /**
     * Apply the rule to a specific node.
     *
     * @param  Class_  $node
     */
    public function refactor(Node $node): ?Node
    {
        // Ensure the class node has a name and a namespace
        if ($node->name === null || $node->namespacedName === null) {
            return null; // Exit early if no name or namespace is found
        }

        $className = $node->name->toString();
        $namespace = $this->getName($node->namespacedName); // Get the full namespace

        // Skip if the class is not a test class
        if (!str_ends_with($className, 'Test')) {
            return null;
        }

        $testedClassName = $this->getTestedClassName($className, $namespace); // Get the tested class name
        $coversMethodName = $this->getCoversMethodName($className); // Get the CoversMethod name

        // Adjust the namespace by replacing 'Tests\Feature' or 'Tests\Unit' with 'App'
        $testedClassNamespace = $this->resolveTestedClassNamespace($namespace, $testedClassName);

        // Check if the class already has the correct 'CoversClass' attribute
        if ($this->hasCorrectCoversClassAttribute($node, $testedClassNamespace)) {
            return null; // If the correct attribute is already present, no modification is needed
        }

        $coversClassAttribute = new AttributeGroup([
            new Attribute(
                new Name('\\PHPUnit\\Framework\\Attributes\\CoversClass'),
                [new Node\Arg(new Node\Expr\ClassConstFetch(
                    new Name($testedClassNamespace), 'class'
                ))]
            ),
        ]);

        if (in_array($className, self::SPECIAL_TEST_NAMES, true)) {
            $coversMethodAttribute = new AttributeGroup([
                new Attribute(
                    new Name('\\PHPUnit\\Framework\\Attributes\\CoversMethod'),
                    [
                        new Node\Arg(new Node\Expr\ClassConstFetch(
                            new Name($testedClassNamespace), 'class'
                        )),
                        new Node\Arg(new Node\Scalar\String_($coversMethodName))
                    ]
                ),
            ]);

            // If there is already an attribute group, modify it, otherwise add new groups
            if (count($node->attrGroups) > 0) {
                $node->attrGroups[0] = $coversClassAttribute; // Replace the first attribute group with CoversClass
                $node->attrGroups[] = $coversMethodAttribute; // Append the CoversMethod attribute
            } else {
                $node->attrGroups[] = $coversClassAttribute; // Attach the CoversClass attribute group
                $node->attrGroups[] = $coversMethodAttribute; // Attach the CoversMethod attribute group
            }
        } else {
            // If there is already an attribute group, modify it, otherwise add a new group
            if (count($node->attrGroups) > 0) {
                $node->attrGroups[0] = $coversClassAttribute; // Replace the first attribute group with the new one
            } else {
                $node->attrGroups[] = $coversClassAttribute; // Attach the new CoversClass attribute group
            }
        }

        return $node;
    }

    /**
     * Get the name of the tested class.
     * If the test class is one of the special names (CreateTest, UpdateTest, etc.), return the parent controller.
     */
    private function getTestedClassName(string $className, string $namespace): string
    {
        // If the class name is one of the special cases, return the parent controller (e.g., LinkController)
        if (in_array($className, self::SPECIAL_TEST_NAMES, true)) {
            return ''; // Return the name of the parent controller
        }

        // For other cases, remove 'Test' from the class name
        return substr($className, 0, -4);
    }

    /**
     * Get the CoversMethod name by stripping 'Test' from the class name and returning the remaining part.
     */
    private function getCoversMethodName(string $className): string
    {
        // Remove 'Test' from the class name and return the method name
        return substr($className, 0, -4); // Return method name without "Test"
    }

    /**
     * Resolve the namespace of the class being tested by replacing 'Tests\Feature' or 'Tests\Unit' with 'App'
     * and appending the remaining namespace after 'Tests\Feature' or 'Tests\Unit'.
     */
    private function resolveTestedClassNamespace(string $namespace, string $testedClassName): string
    {
        // Handle both 'Tests\Feature' and 'Tests\Unit' namespaces
        if (strpos($namespace, 'Tests\Feature') !== false) {
            $remainingNamespace = str_replace('Tests\Feature', 'App', $namespace);
        } elseif (strpos($namespace, 'Tests\Unit') !== false) {
            $remainingNamespace = str_replace('Tests\Unit', 'App', $namespace);
        } else {
            // If the namespace does not match either 'Tests\Feature' or 'Tests\Unit', return a default namespace
            return '\\App\\' . $testedClassName;
        }

        // Remove the last part, which is the test class name (with "Test" suffix)
        $testedNamespaceParts = explode('\\', $remainingNamespace);
        array_pop($testedNamespaceParts); // Pop off the test class (e.g., CreateTest)

        // Rebuild the namespace and append the tested class name, only if it's not empty
        $namespace = '\\' . implode('\\', $testedNamespaceParts);
        return $testedClassName ? $namespace . '\\' . $testedClassName : $namespace;
    }

    /**
     * Check if the correct CoversClass attribute already exists.
     */
    private function hasCorrectCoversClassAttribute(Class_ $node, string $testedClassNamespace): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, CoversClass::class)) {
                    foreach ($attr->args as $arg) {
                        if ($arg->value instanceof Node\Expr\ClassConstFetch) {
                            $existingClassName = $this->getName($arg->value->class);
                            if ($existingClassName === $testedClassNamespace) {
                                return true; // Attribute is correct
                            }
                        }
                    }
                }
            }
        }

        return false; // Correct attribute not found
    }

    /**
     * This method helps others understand the rule
     * and generates documentation.
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Fail if test classes do not have a CoversClass attribute pointing to the tested class and a CoversMethod attribute for special test classes.', [
                new CodeSample(
                    // Code before
                    <<<'CODE_SAMPLE'
namespace Tests\Feature\Http\Controllers\Admin\LinkController;

class CreateTest extends TestCase
{
}
CODE_SAMPLE
                    ,
                    // Code after
                    <<<'CODE_SAMPLE'
namespace Tests\Feature\Http\Controllers\Admin\LinkController;

#[\PHPUnit\Framework\Attributes\CoversClass(\App\Http\Controllers\Admin\LinkController::class)]
#[\PHPUnit\Framework\Attributes\CoversMethod(\App\Http\Controllers\Admin\LinkController::class, "Create")]
class CreateTest extends TestCase
{
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
