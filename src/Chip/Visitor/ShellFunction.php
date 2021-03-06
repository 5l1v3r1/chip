<?php
/**
 * Created by PhpStorm.
 * User: phithon
 * Date: 2019/1/18
 * Time: 2:20
 */

namespace Chip\Visitor;

use Chip\BaseVisitor;
use Chip\Traits\Variable;
use Chip\Traits\Walker\FunctionWalker;
use PhpParser\Node\Expr\FuncCall;

class ShellFunction extends BaseVisitor
{
    use Variable, FunctionWalker;

    protected $checkNodeClass = [
        FuncCall::class
    ];

    protected $whitelistFunctions = [
        'system',
        'shell_exec',
        'exec',
        'passthru',
        'popen',
        'proc_open',
    ];

    /**
     * @param  FuncCall $node
     */
    public function process($node)
    {
        if (empty($node->args)) {
            return;
        }

        $arg = $node->args[0]->value;
        if ($this->hasDynamicExpr($arg)) {
            $this->storage->critical($node, __CLASS__, '执行的命令中包含动态变量或函数，可能有远程命令执行风险');
        } else {
            $this->storage->info($node, __CLASS__, '执行命令');
        }
    }
}
