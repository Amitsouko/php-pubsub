<?php 

Namespace Lib;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;


require __DIR__.'/../vendor/autoload.php';

class PublisherCommand extends Command
{
    protected function configure()
    {
        $this->setName('publisher')
             ->setDescription('launch the client');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();
        $questionChannel = new Question('<info>Please enter the channel of the websocket (default: test)</info>: ', 'test');
        $channelName = $helper->ask($input, $output, $questionChannel);

        $questionMessage = new Question('<info>Message to send</info>: ');
        $message = $helper->ask($input, $output, $questionMessage);
        
        $client = new WebSocketClient('ws://localhost:8080');

        $client->sendToChannel($channelName, $message);
        $client->close();

        return Command::SUCCESS;
    }
}
