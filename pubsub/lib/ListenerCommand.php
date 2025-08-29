<?php 

Namespace Lib;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;

require __DIR__.'/../vendor/autoload.php';

class ListenerCommand extends Command
{
    protected function configure()
    {
        $this->setName('listener')
             ->setDescription('launch the client');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();
        $question = new Question('<info>Please enter the list of channels separated with "," (default: test)</info>: ', 'test');
        $answer = $helper->ask($input, $output, $question);
        $channelNames = explode(",", $answer);

        $client = new \Lib\WebSocketClient('ws://localhost:8080');

        foreach($channelNames as $channelName) {
            $client->subscribeToChannel($channelName);
            $output->writeln('<info>Subscribed to</info>: ' . $channelName);
        }
        
        $client->connectAndListen();

        return Command::SUCCESS;
    }
}
