<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class ExpireGatewayOrders extends Command
{
    protected function configure(): void
    {
        $this->setName('gateway:expire')
            ->setDescription('Auto-cancel pending gateway orders that have passed their expiry time');
    }

    protected function execute(Input $input, Output $output): int
    {
        $count = Db::name('gateway_orders')
            ->where('status', 'pending')
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->update([
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        Log::info('gateway.expire.completed', ['cancelled' => $count]);
        $output->writeln("Expired {$count} pending gateway order(s).");

        return 0;
    }
}
