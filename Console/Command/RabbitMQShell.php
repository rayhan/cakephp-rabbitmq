<?php
App::uses('ShellDispatcher', 'Console');
use PhpAmqpLib\Connection\AMQPConnection;


class RabbitMQShell extends Shell
{

    public function main()
    {

        $exchange = 'default';
        $queue = 'default';
        $consumer_tag = 'consumer';

        $conn = new AMQPConnection(RABBITMQ_HOST, RABBITMQ_PORT, RABBITMQ_USER, RABBITMQ_PASS, RABBITMQ_VHOST);
        $ch = $conn->channel();
        $ch->queue_declare($queue, false, true, false, false);
        $ch->exchange_declare($exchange, 'direct', false, true, false);
        $ch->queue_bind($queue, $exchange);

        function process_message($msg)
        {
            $msg_body = json_decode($msg->body,true);
            if(isset($msg_body['params']) && is_array($msg_body['params'])){
                $msg_body['params'] = json_encode($msg_body['params']);
            }
            $msg_body = array_values($msg_body);
            $dispatcher = new ShellDispatcher($msg_body, false);
            try {
                $dispatcher->dispatch();
            } catch (Exception $e) {
                RabbitMQ::publish(json_decode($msg->body,true), ['exchange'=>'requeueable', 'queue'=>'requeueable_messages']);
                $newMessage[] = $msg->body;
                $newMessage[] = '==>';
                $newMessage[] = $e->getMessage();
                $newMessage[] = $e->getFile();
                $newMessage[] = $e->getLine();
                $newMessage[] = $e->getTraceAsString();
                $newMessage[] = $e->getCode();
                $newMessage[] = $e->getPrevious();
                RabbitMQ::publish($newMessage, ['exchange'=>'unprocessed', 'queue'=>'unprocessed_messages']);
                EmailSender::sendEmail('elisio.leonardo@gmail.com', $msg->body, $newMessage);
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
