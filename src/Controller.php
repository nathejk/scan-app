<?php
namespace Nathejk;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller
{
    protected $context = ['title' => 'Nathejk 2022'];

    public function indexAction(Application $app, Request $request)
    {
        $text = file_get_contents(__DIR__ . '/../README.md');
        return \Michelf\MarkdownExtra::defaultTransform($text);
    }

    public function listAction(Application $app, Request $request)
    {
        for ($i = 0; $i <= 999; $i++) {
            $secret = substr(md5($this->context['title'] . $i), 10, 4);
            $qr = (new Entity\QR)
                ->setSecret($secret);
            $app['orm.em']->persist($qr);
        }
        $app['orm.em']->flush();

        $txt = "number;url\n";
        $qrs = $app['orm.em']->getRepository(Entity\QR::class)->findAll();
        foreach ($qrs as $qr) {
            $txt .= "{$qr->getId()};https://scan.nathejk.dk/{$qr->getId()}/{$qr->getSecret()}\n";
        }
        return new Response($txt, 200, ["Content-Type" => "text/plain"]);;
    }
    public function listMembers(Application $app, Request $request, $phone)
    {
        $members =  $app['repo']->findMembersByPhone($phone);
        
        return new Response(count($members) ? json_encode($members) : $phone, 200, ["Content-Type" => "application/json"]);
    }

    public function loginAction(Application $app, Request $request)
    {
        return 'scan';
    }

    public function scanAction(Application $app, Request $request, $qrId, $secret)
    {
        if (!$user = $this->getLoggedInUser($app, $request)) {
            return 'fail';
        }
        
        if (is_string($user) || !$user instanceof \stdClass) {
            // $user this is not a user, but a template
            return $user;
        }
        $qr = $app['orm.em']->getRepository(Entity\QR::class)->findOneBy(['id' => $qrId, 'secret' => $secret]);
        if (!$qr) {
            return $app['twig']->render('error.twig', $this->context);
        }
        if (!$qr->getNumber()) {

            $meta = (object)[
                'httpheaders' => array_diff_key($request->server->getHeaders(), array_flip(['COOKIE', 'X_API_KEY'])),
                'producer' => 'scan-app',
            ];
            if ($number = $request->query->get('number')) {
                $teamId = $app['repo']->findTeamIdByNumber($number);
                $team = $app['repo']->findTeam($teamId);
                return $app['twig']->render('map-qr.twig', $this->context + ["team" => $team]);
            } elseif ($number = $request->query->get('confirmed')) {
                $qr
                    ->setNumber($number)
                    ->setMapCreateTime(new \DateTime)
                    ->setMapCreateByPhone($user->phone);

                $body = (object)[
                    'qrId' => $qrId,
                    'teamId' => $app['repo']->findTeamIdByNumber($number),
                    'teamNumber' => $number,
                    'scannerId' => $user->id,
                    'scannerPhone' => $user->phone,
                ];
                $app['stan']->publish('nathejk', (new Stan\Message)->setType('qr.registered')->setBody($body)->setMeta($meta));
                
                $app['orm.em']->persist($qr);
                $app['orm.em']->flush();
            } else {
                $body = (object)[
                    'qrId' => $qrId,
                    'scannerId' => $user->id,
                    'scannerPhone' => $user->phone,
                ];
                $app['stan']->publish('nathejk', (new Stan\Message)->setType('qr.found')->setBody($body)->setMeta($meta));

                return $app['twig']->render('map-qr.twig', $this->context);
            }
        }

        $teamId = $app['repo']->findTeamIdByNumber($qr->getNumber());
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
            if ($user->team->typeName??'' == 'slut') {
                $app['repo']->finish($team);
            }
            $app['repo']->saveScan($team, $user, $loc);
            $this->scan($app, $request, $qrId, $team, $user, $loc);
            // reload team to reflec scanning
            $this->context['team'] = $app['repo']->findTeam($team->id);
            return $app['twig']->render('contact.twig', $this->context);
        }
    }

    public function scan($app, $request, $qrId, $team, $member, $loc)
    {
        $body = (object)[
            'qrId' => $qrId,
            'teamId' => $team->id,
            'teamNumber' => $team->armNumber,
            'scannerId' => $member->id,
            'scannerPhone' => $member->phone,
        ];
        $meta = (object)[
            'httpheaders' => array_diff_key($request->server->getHeaders(), array_flip(['COOKIE', 'X_API_KEY'])),
            'producer' => 'scan-app',
        ];
        if (strstr($loc, ":") !== false) {
            list($lat, $lon) = explode(':', "$loc:");
            $body->location = ['lat' => $lat, 'lon' => $lon];
        } else {
            $body->position = $loc;
        }
        $message = (new Stan\Message)->setType('qr.scanned')->setBody($body)->setMeta($meta);

        $app['stan']->publish('nathejk', $message);
/*
        $this->_stan->publish($channel, [
        ]);
        $this->_id = Uuid::uuid4()->toString();
        return;
        if (!strpos($loc, ':')) return;

        $now = new \DateTime('NOW');
        $connectionOptions = new \Nats\ConnectionOptions();
        $connectionOptions
            ->setHost('nats')
            ->setPort(4222);
        $c = new \Nats\Connection($connectionOptions);
        $c->connect();
        $c->publish("geoEvents", json_encode([
            'type' => "caught",
            'charter' => $member->team->title,
            'gang' => $member->team->title,
            'timestamp' => $now->format(\DateTime::ISO8601),
        ]));
        $c->close();
 */
    }

    public function logoutAction(Application $app, Request $request)
    {
        setcookie('nh');
        //dd, $member->id . ':' . md5('kaal' . $member->id), time() + $duration, '/');
        return 'ok';
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
