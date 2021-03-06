<?php
/**
 * Created by PhpStorm.
 * User: phithon
 * Date: 2019/1/20
 * Time: 2:56
 */

namespace Chip\Visitor;

use Chip\BaseVisitor;
use Chip\Exception\NodeTypeException;
use Chip\Traits\TypeHelper;
use Chip\Traits\Variable;
use Chip\Traits\Walker\MethodWalker;
use PhpParser\Node;

class MethodCallback extends BaseVisitor
{
    use Variable, TypeHelper, MethodWalker;

    protected $checkNodeClass = [
        Node\Expr\MethodCall::class
    ];

    protected $sensitiveMethodName = [
        'uasort'                   => [0],
        'uksort'                   => [0],
        'set_local_infile_handler' => [0],
        'sqlitecreateaggregate'    => [1, 2],
        'sqlitecreatecollation'    => [1],
        'sqlitecreatefunction'     => [1],
        'createcollation'          => [1],
        'fetchall'                 => [1],
        'createfunction'           => [1],
    ];

    protected function getWhitelistMethods()
    {
        return array_keys($this->sensitiveMethodName);
    }

    /**
     * @param Node\Expr\MethodCall $node
     */
    public function process($node)
    {
        $fname = $this->fname;
        if ($fname === 'fetchall') {
            $this->dealWithFetchAll($node);
            return;
        }

        foreach ($this->sensitiveMethodName[$fname] as $pos) {
            $pos = $pos >= 0 ? $pos : count($node->args) + $pos;
            foreach ($node->args as $key => $arg) {
                if ($arg->unpack && $key <= $pos) {
                    $this->storage->danger($node, __CLASS__, "{$fname}第{$key}个参数包含不确定数量的参数，可能执行动态回调函数，存在远程代码执行的隐患");
                    continue 2;
                }
            }

            if (array_key_exists($pos, $node->args)) {
                $arg = $node->args[$pos];
            } else {
                continue;
            }

            if ($this->hasDynamicExpr($arg->value)) {
                $this->storage->danger($node, __CLASS__, "{$fname}方法第{$pos}个参数包含动态变量或函数，可能有远程代码执行的隐患");
            } elseif (!($arg->value instanceof Node\Expr\Closure)) {
                $this->storage->warning($node, __CLASS__, "{$fname}方法第{$pos}个参数，请使用闭包函数");
            }
        }
    }

    /**
     * @param Node\Expr\MethodCall $node
     */
    protected function dealWithFetchAll(Node\Expr\MethodCall $node)
    {
        if (empty($node->args)) {
            return;
        } elseif (count($node->args) == 1) {
            if ($node->args[0]->unpack) {
                $this->storage->danger($node, __CLASS__, "fetchAll第0个参数包含不确定数量的参数，可能执行动态回调函数，存在远程代码执行的隐患");
            }
            return;
        }

        $fetchStyle = $node->args[0]->value;
        $fetchArgument = $node->args[1]->value;
        if ($fetchStyle instanceof Node\Expr\ClassConstFetch && $this->isName($fetchStyle->class) && $fetchStyle->class->toLowerString() === 'pdo' && $this->isIdentifier($fetchStyle->name) && in_array($fetchStyle->name->name, ['FETCH_CLASS', 'FETCH_COLUMN'], true)) {
            return;
        } elseif ($this->isNumber($fetchStyle) && in_array($fetchStyle->value, [7, 8], true)) {
            return;
        } elseif ($this->hasDynamicExpr($fetchArgument)) {
            $this->storage->danger($node, __CLASS__, "fetchAll方法第1个参数包含动态变量或函数，可能有远程代码执行的隐患");
            return;
        } elseif (!$this->isClosure($fetchArgument)) {
            $this->storage->warning($node, __CLASS__, "fetchAll方法第1个参数，请使用闭包函数");
            return;
        }
    }
}
