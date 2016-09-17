<?php
namespace Nathejk\Scan;

use Symfony\Component\HttpFoundation\Request;

class Controller
{
    protected $context = ['title' => 'Nathejk 2016'];

    public function indexAction(Application $app, Request $request)
    {
        $text = file_get_contents(__DIR__ . '/../README.md');
        return \Michelf\MarkdownExtra::defaultTransform($text);
    }

    public function loginAction(Application $app, Request $request)
    {
        return 'scan';
    }

    public function scanAction(Application $app, Request $request, $teamId, $checksum)
    {
        if (!$user = $this->getLoggedInUser($app, $request)) {
            return 'fail';
        }
        
        if (is_string($user) || !$user instanceof \stdClass) {
            // $user this is not a user, but a template
            return $user;
        }

        $team = $app['repo']->findTeam($teamId);
        if (!$team) {
            return $app['twig']->render('error.twig', $this->context);
        }
        if ($team->parentTeamId) {
            $team = $app['repo']->findTeam($team->parentTeamId);
        }

        $this->context += ['team' => $team];

        $loc = $request->query->get('location');
        if (isset($_POST['choose'])) {
            return $app['twig']->render('choose.twig', $this->context);
        }
        else if (empty($loc)) {
            return $app['twig']->render('coordinates.twig', $this->context);
        } else {
            $app['repo']->saveScan($team, $user, $loc);
            $this->scan($team, $user, $loc);
            // reload team to reflec scanning
            $this->context['team'] = $app['repo']->findTeam($team->id);
            return $app['twig']->render('contact.twig', $this->context);
        }
    }

    public function scan($team, $member, $loc)
    {
        if (!strpos($loc, ':')) return;

        $now = new \DateTime('NOW');
        list($lat, $lon) = explode(':', $loc);
        $connectionOptions = new \Nats\ConnectionOptions();
        $connectionOptions
            ->setHost('nats')
            ->setPort(4222);
        $c = new \Nats\Connection($connectionOptions);
        $c->connect();
        $c->publish("geoEvents", json_encode([
            'type' => "caught",
            'location' => ['lat' => $lat, 'lon' => $lon],
            'patrol' => $team->armNumber,
            'bandit' => $member->number,
            'charter' => $member->team->title,
            'gang' => $member->team->title,
            'timestamp' => $now->format(\DateTime::ISO8601),
        ]));
        $c->close();
    }

    public function getLoggedInUser($app, $request)
    {
        $cookie = isset($_COOKIE['nh']) ? $_COOKIE['nh'] : ':';
        list($id, $checksum) = explode(':', $cookie);

        $member = null;
        $members = array();
        if (!empty($id)) {
            $member = $app['repo']->findMember($id);
        }
        if (!$member && !empty($_POST['phone'])) {
            $members = $app['repo']->findMembersByPhone($_POST['phone']);
            if (count($members) == 1) {
                $member = array_shift($members);
            }
        }
        if (!$member && !isset($_POST['memberId'])) {
            if (count($members)) {
                $this->context += ['members' => $members];
                return $app['twig']->render('choose.twig', $this->context);
            } else {
                return $app['twig']->render('login.twig', $this->context);
            }
        } else {
            if (isset($_POST['memberId'])) {
                $member = $app['repo']->findMember($_POST['memberId']);
                var_dump($id, $member);
            }
            $this->context += [
                'member' => $member,
                'members' => $app['repo']->findMembersByPhone($member->phone)
            ];
            $duration = count($members) > 1 ? 60*30 : 60*60*24*3; 
            setcookie('nh', $member->id . ':' . md5('kaal' . $member->id), time() + $duration, '/');
            if (isset($_POST['memberId'])) {
                return $app->redirect($request->getUri());
            }
        }
        $this->context += ['member' => $member];
        return $member;
    }
}
