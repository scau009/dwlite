<?php

namespace App\Controller\Admin;

use Symfony\Component\Routing\Attribute\Route;
use Zenstruck\Messenger\Monitor\Controller\MessengerMonitorController as BaseMessengerMonitorController;

/**
 * Messenger 队列监控面板.
 */
#[Route('/_debug/messenger')]
final class MessengerMonitorController extends BaseMessengerMonitorController
{
}
