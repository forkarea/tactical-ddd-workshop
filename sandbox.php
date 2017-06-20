<?php
declare(strict_types=1);

use Common\EventDispatcher\EventCliLogger;
use Common\EventDispatcher\EventDispatcher;
use MeetupOrganizing\Application\RsvpYesForOrganizerWhenMeetupScheduled;
use MeetupOrganizing\Domain\Model\Meetup\Meetup;
use MeetupOrganizing\Domain\Model\Meetup\MeetupId;
use MeetupOrganizing\Domain\Model\Meetup\MeetupScheduled;
use MeetupOrganizing\Domain\Model\Meetup\ScheduledDate;
use MeetupOrganizing\Domain\Model\Rsvp\RsvpedNo;
use MeetupOrganizing\Domain\Model\Rsvp\RsvpedYes;
use MeetupOrganizing\Domain\Model\Rsvp\RsvpId;
use MeetupOrganizing\Infrastructure\Membership\RemoteUserIdFactory;
use MeetupOrganizing\Domain\Model\Meetup\WorkingTitle;
use MeetupOrganizing\Domain\Model\MeetupGroup\MeetupGroup;
use MeetupOrganizing\Domain\Model\Rsvp\Rsvp;
use Membership\Domain\Model\User\User;
use MeetupOrganizing\Infrastructure\Persistence\InMemoryMeetupGroupRepository;
use Membership\Infrastructure\Persistence\InMemoryUserRepository;
use Ramsey\Uuid\Uuid;

require __DIR__ . '/vendor/autoload.php';

$userRepository = new InMemoryUserRepository();
$meetupGroupRepository = new InMemoryMeetupGroupRepository();

$eventDispatcher = new EventDispatcher();
//$eventDispatcher->subscribeToAllEvents(new EventCliLogger());

/*
 * In the "Membership" context
 */
$user = new User(
    $userRepository->nextIdentity(),
    'Matthias Noback',
    'matthiasnoback@gmail.com'
);
$userRepository->add($user);

/*
 * In the "Meetup Organizing" context
 */
$userIdInSession = (string)$user->userId();

$meetupGroup = new MeetupGroup(
    $meetupGroupRepository->nextIdentity(),
    'Akeneo Meetups'
);
$meetupGroupRepository->add($meetupGroup);

$userIdFactory = new RemoteUserIdFactory();
$organizerId = $userIdFactory->createOrganizerId($userIdInSession);

//dump($meetup); // i.e. persist
//
//$eventDispatcher->registerSubscriber(
//    MeetupScheduled::class,
//    new RsvpYesForOrganizerWhenMeetupScheduled($userIdFactory)
//);

$meetups = [];

$eventDispatcher->registerSubscriber(MeetupScheduled::class, function(MeetupScheduled $event) use (&$meetups) {
    $meetups[(string)$event->meetupId()] = [
        'title' => (string)$event->workingTitle(),
        'numberOfAttendees' => 0
    ];
});

$eventDispatcher->registerSubscriber(RsvpedYes::class, function (RsvpedYes $event) use (&$meetups) {
    $currentNumberOfAttendees = $meetups[(string)$event->meetupId()]['numberOfAttendees'];
    $meetups[(string)$event->meetupId()]['numberOfAttendees'] = $currentNumberOfAttendees + 1;
    dump($meetups);
});
$eventDispatcher->registerSubscriber(RsvpedNo::class, function (RsvpedNo $event) use (&$meetups) {
    $currentNumberOfAttendees = $meetups[(string)$event->meetupId()]['numberOfAttendees'];
    $meetups[(string)$event->meetupId()]['numberOfAttendees'] = $currentNumberOfAttendees - 1;
    dump($meetups);
});

$meetupId = MeetupId::fromString((string)Uuid::uuid4());
$meetup = Meetup::schedule(
    $meetupId,
    $meetupGroup->meetupGroupId(),
    $organizerId,
    new WorkingTitle('May Meetup'),
    ScheduledDate::fromDateTime(new \DateTimeImmutable('2017-05-05 19:00'))
);

$attendeeId = $userIdFactory->createAttendeeId($userIdInSession);
$rsvpId = RsvpId::fromString((string)Uuid::uuid4());
$rsvp = Rsvp::yes($rsvpId, $meetupId, $attendeeId);

$eventDispatcher->dispatchAll($meetup->popRecordedEvents());
$eventDispatcher->dispatchAll($rsvp->popRecordedEvents());

//$meetup->cancel();
//
//$events = $meetup->popRecordedEvents();
//
//dump($events);
//
//$reconstitutedMeetup = Meetup::reconstitute($events);
//
//\PHPUnit_Framework_Assert::assertEquals($meetup, $reconstitutedMeetup);
