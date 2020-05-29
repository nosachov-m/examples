<?php

namespace BookingBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Debug\Exception\ContextErrorException;

class ReminderCommand extends ContainerAwareCommand
{
    /**
     * @var string
     */
    private $messageEmailsNotSended = 'Reminder emails not sended';

    /**
     * @var string
     */
    private $messageEmailsSended = 'Reminder emails sended';

    protected function configure()
    {
        $this->setDefinition([])
            ->setDescription('Send reminder for bookings 3 weeks after date')
            ->setName('booking:tasks:reminder:send');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        try {
            $threeWeeksBefore = new \DateTime('-21 days');
            $threeWeeksGte = clone $threeWeeksBefore->setTime(0, 0, 0);
            $threeWeeksLte = clone $threeWeeksBefore->setTime(23, 59, 59);

            $bookings = $container->get('booking.booking.booking.repository')->findByDateFeedback(
                $threeWeeksGte,
                $threeWeeksLte
            );

            $container->get('booking.core.mailchimp_manager')->sendRemindFeedbackEmail($bookings);
        } catch (ContextErrorException $e) {
            $output->writeln("<info>$this->messageEmailsNotSended</info>");

            return false;
        }

        $output->writeln("<info>$this->messageEmailsSended</info>");
        $output->writeln('<info>[Successfuly sended]</info>');
    }
}
