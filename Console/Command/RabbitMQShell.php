<?php
App::uses('ShellDispatcher', 'Console');
use PhpAmqpLib\Connection\AMQPConnection;

/**
 * CakePHP-RabbiMQ Shell File
 *
 * Use to manage the workers via CLI
 *
 * PHP version 7
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Elisio Leonardo <elisio.leonardo@gmail.com>
 * @copyright     Copyright 2016, Elisio Leonardo <elisio.leonardo@gmail.com>
 * @link          http://infomoz.net
 * @package       RabbitMQ
 * @subpackage      RabbitMQ.Console.Command
 * @since         0.5
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class RabbitMQShell extends Shell
{


    public function main()
    {

        $exchange = 'router';
        $queue = 'default';
        $consumer_tag = 'consumer';

        $conn = new AMQPConnection(RABBITMQ_HOST, RABBITMQ_PORT, RABBITMQ_USER, RABBITMQ_PASS, RABBITMQ_VHOST);
        $ch = $conn->channel();
        $ch->queue_declare($queue, false, true, false, false);
        $ch->exchange_declare($exchange, 'direct', false, true, false);
        $ch->queue_bind($queue, $exchange);

        function process_message($msg)
        {
            $args = explode(' ', $msg->body);
            $dispatcher = new ShellDispatcher($args, false);
            try {
                $dispatcher->dispatch();
            } catch (Exception $e) {
                $newMessage = explode(' ', $msg->body);
                RabbitMQ::publish($newMessage, 'unprocessed', 'requeueable_messages');
                $newMessage[] = '==>';
                $newMessage[] = $e->getMessage();
                $newMessage[] = $e->getFile();
                $newMessage[] = $e->getLine();
                $newMessage[] = $e->getTraceAsString();
                $newMessage[] = $e->getCode();
                $newMessage[] = $e->getPrevious();
                RabbitMQ::publish($newMessage, 'unprocessed', 'unprocessed_messages');
                EmailSender::sendEmail('elisio.leonardo@gmail.com', $msg->body, $newMessage);
                throw new Exception($e);
            }
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

            // Send a message with the string "quit" to cancel the consumer.
            if ($msg->body === 'quit') {
                $msg->delivery_info['channel']->basic_cancel($msg->delivery_info['consumer_tag']);
            }
        }
        $ch->basic_qos(null, 1, null);
        $ch->basic_consume($queue, $consumer_tag, false, false, false, false, 'process_message');

        function shutdown($ch, $conn)
        {
            $ch->close();
            $conn->close();
        }

        register_shutdown_function('shutdown', $ch, $conn);

// Loop as long as the channel has callbacks registered
        while (count($ch->callbacks)) {
            $ch->wait();
        };
    }


}
