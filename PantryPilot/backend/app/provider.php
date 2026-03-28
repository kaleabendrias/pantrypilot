<?php

return [
    app\infrastructure\filesystem\LocalFileStorageAdapter::class => function () {
        return new app\infrastructure\filesystem\LocalFileStorageAdapter('/var/www/html/runtime/uploads');
    },
    app\infrastructure\time\ClockInterface::class => function () {
        return new app\infrastructure\time\EnvClock();
    },
];
