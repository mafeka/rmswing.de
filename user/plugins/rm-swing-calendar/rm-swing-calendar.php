<?php
namespace Grav\Plugin;

use function foo\func;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

use ICal\ICal;

/**
 * Class RMSwingCalendarPlugin
 * @package Grav\Plugin
 */
class RMSwingCalendarPlugin extends Plugin
{

    private const ICAL_SETTINGS = [
        'defaultSpan'                   => 2,     // Default value
        'defaultTimeZone'               => 'UTC',
        'defaultWeekStart'              => 'MO',  // Default value
        'disableCharacterReplacement'   => false, // Default value
        'filterDaysAfter'               => null,  // Default value
        'filterDaysBefore'              => null,  // Default value
        'replaceWindowsTimeZoneIds'     => false, // Default value
        'skipRecurrence'                => false, // Default value
        'useTimeZoneWithRRules'         => false, // Default value
    ];

    private const PREFIX = 'plugins.rm-swing-calendar.';

    private const ALLOWED_PARAMETERS = [
        'source'  => [
            'type' => 'string',
            'default' => 'all',
        ],
        'number'  => [
            'type' => 'integer',
            'default' => 10,
        ],
        'offset'  => [
            'type' => 'integer',
            'default' => 0,
        ],
    ];

    /** @var array The used queries*/
    private $queries = [];

    /** @var string Date format */
    private $dateFormat = '';

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        $path = $this->grav['uri']->path();
        $route = $this->config->get(self::PREFIX . 'route');

         if ($route && $route == $path) {
            $this->enable([
                'onPageInitialized' => ['onPageInitialized', 0]
            ]);
        }
    }

    /**
     * Let's rock an' roll
     */
    public function onPageInitialized()
    {
        header("Content-Type: application/json");

        $sources = $this->config->get(self::PREFIX . 'calendar-sources');

        $this->verifyParameters($this->grav['uri']);

        /** @var Promise\Promise[] $promises */
        $promises = [];
        /** @var \GuzzleHttp\Client $http_client */
        $http_client = new Client();
        /** @var string $ics_raw */
        $ics_raw = '';
        /** @var \ICal\Event[] $events */
        $events = [];

        // Calender source specified by parameter
        if (array_key_exists($this->queries['source'], $sources)){

            $source = $this->queries['source'];
            $url = $sources[$source]['urls']['ics'];
            $request = $http_client->get($url);
            $ics_raw = $request->getBody();

            /** @var \ICal\ICal $ICal */
            $ICal = $this->getICal($ics_raw);

            /** @var \ICal\Event[] $events_raw */
            $events_raw = array_slice(
                $ICal->eventsFromRange(),
                $this->queries['offset'],
                $this->queries['number']
            );

            $events = [];

            foreach ($events_raw as $event) {
                $events[$event->uid] = [
                    'event'         => $this->prettifyEvent($event),
                    'category'      => $sources[$source]['category'],
                    'public_url'    => $sources[$source]['urls']['web']
                ];
            }

        } else { // No source specified, let's grab 'em all

            foreach ($sources as $cal => $source) {
                $url = $source['urls']['ics'];
                $promises[$cal] = $http_client->getAsync($url);
            }
            $results = Promise\settle($promises)->wait(); // Waits until concurrent downloads have finished

            foreach ($results as $name => $request) {

                $ICal = $this->getICal($results[$name]['value']->getBody());

                // We'll slice here, to keep the array nice and small.
                // We'll slice the results later again.
                foreach (array_slice(
                             $ICal->eventsFromRange(),
                             $this->queries['offset'],
                             $this->queries['number']
                         ) as $raw_event) {

                    $events[$raw_event->uid] = [
                        'event'         => $this->prettifyEvent($raw_event),
                        'category'      => $sources[$name]['category'],
                        'public_url'    => $sources[$name]['urls']['web']
                    ];

                }
            }

            // Since we've merge different sources, events need to be resorted.
            uasort($events, function($a, $b) {
                return strcmp(
                    $a['event']['start'],
                    $b['event']['start']
                );
            });



        }

        echo json_encode(
             array_slice(
                 $events,
                 $this->queries['offset'],
                 $this->queries['number']
            ),
            JSON_PRETTY_PRINT|JSON_OBJECT_AS_ARRAY
        );
        exit;

    }

    /**
     * Verifies and sanitizes parameters
     *
     * @param \Grav\Common\Uri $uri
     */
    private function verifyParameters(\Grav\Common\Uri $uri): void{

        foreach (self::ALLOWED_PARAMETERS as $name => $data) {

            $value = $uri->query($name, false);

            if (is_null($value)) {
                // Parameter not set, using default value from CONST
                $this->queries[$name] = $data['default'];
            } else {
                //Parameter set, casting it to the right type
                settype($value, $data['type']);
                $this->queries[$name] = $value;
            }

        }

    }

    /**
     * Returns ICal instance for given input
     *
     * @param string $raw_fucking_string String with ics data
     * @return ICal
     */
    private function getICal(string $raw_fucking_string): ICal {
        $parser = new iCal('', self::ICAL_SETTINGS);
        $parser->initString($raw_fucking_string);
        return $parser;
    }


    /**
     * Filters out a bunch of crap, returns array with the data we're actually going to use
     *
     * @param \ICal\Event $event_raw
     * @return array
     */
    private function prettifyEvent(\ICal\Event $event_raw): array {

        return [
            'description'   => $event_raw->description,
            'start'         => $event_raw->dtstart,
            'location'      => $event_raw->location,
            'end'           => $event_raw->dtend,
            'organizer'     => $event_raw->organizer,
            'summary'       => $event_raw->summary,
            'cal'           => $event_raw->uid
        ];
    }

    private function getPrettyTime(string $input): string {

    }

}
