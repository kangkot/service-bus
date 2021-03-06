<?php
/*
 * This file is part of the prooph/service-bus.
 * (c) Alexander Miertsch <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 23.09.14 - 20:48
 */

namespace Prooph\ServiceBusTest;

use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\InvokeStrategy\ForwardToMessageDispatcherStrategy;
use Prooph\ServiceBus\Message\FromMessageTranslator;
use Prooph\ServiceBus\Message\InMemoryMessageDispatcher;
use Prooph\ServiceBus\Message\ToMessageTranslator;
use Prooph\ServiceBus\Router\EventRouter;
use Prooph\ServiceBus\ServiceLocator\Zf2ServiceLocatorProxy;
use Prooph\ServiceBusTest\Mock\SomethingDone;
use Prooph\ServiceBusTest\Mock\SomethingDoneInvokeStrategy;
use Prooph\ServiceBusTest\Mock\SomethingDoneListener;
use Zend\ServiceManager\ServiceManager;

/**
 * Class EventBusTest
 *
 * @package Prooph\ServiceBusTest
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
class EventBusTest extends TestCase
{
    /**
     * @var SomethingDoneListener
     */
    protected $somethingDoneListener;

    /**
     * @var EventBus
     */
    protected $eventBus;

    protected function setUp()
    {
        $this->somethingDoneListener = new SomethingDoneListener();

        $this->eventBus = new EventBus();

        $router = new EventRouter();

        //Route the event to a message dispatcher which then dispatches the message on a second bus
        $router->route('Prooph\ServiceBusTest\Mock\SomethingDone')->to($this->setUpMessageDispatcher());

        $this->eventBus->utilize($router);

        //Register message forwarder which translates event to message and forward it to the message dispatcher
        $this->eventBus->utilize(new ForwardToMessageDispatcherStrategy(new ToMessageTranslator()));
    }

    /**
     * @return InMemoryMessageDispatcher
     */
    protected function setUpMessageDispatcher()
    {
        $eventBus = new EventBus();

        //Translate message back to event
        $eventBus->utilize(new FromMessageTranslator());

        $router = new EventRouter();

        $router->route('Prooph\ServiceBusTest\Mock\SomethingDone')->to('something_done_listener');

        $eventBus->utilize($router);

        //Set up a ZF2 ServiceLocator to locate the event listener
        //In this scenario it would be easier to route the event directly to the listener instance
        //but we want to test the full stack
        $serviceLocator = new ServiceManager();

        $serviceLocator->setService('something_done_listener', $this->somethingDoneListener);

        $eventBus->utilize(new Zf2ServiceLocatorProxy($serviceLocator));

        //Register appropriate invoke strategy
        $eventBus->utilize(new SomethingDoneInvokeStrategy());

        //Set up message dispatcher with a prepared command bus that can dispatch the message to command handler
        $messageDispatcher = new InMemoryMessageDispatcher(new CommandBus(), $eventBus);

        return $messageDispatcher;
    }

    /**
     * @test
     */
    public function it_forwards_an_event_to_message_dispatcher_and_than_to_listener()
    {
        $somethingDone = SomethingDone::fromData('dispatch me');

        $this->eventBus->dispatch($somethingDone);

        $this->assertEquals(array('data' => 'dispatch me'), $this->somethingDoneListener->lastEvent()->payload());
    }
}
 