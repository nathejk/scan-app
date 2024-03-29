<?php
namespace Nathejk;

class Repository
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function findTeamIdByNumber($number)
    {
        $sql = "SELECT id FROM nathejk_team WHERE teamNumber = :number";
        $row = $this->app['dbs']['monolith']->executeQuery($sql, ['number' => $number])->fetchObject();

        return $row ? $row->id : null;
    }

    /**
     * @return {id, armNumber, parentTeamId, catchCount, contactCount, photoId, noticeText, title, teamNumber}
     */
    public function findTeam($teamId)
    {
        $countSql = "SELECT COUNT(*) FROM nathejk_member WHERE teamId = team.id AND deletedUts = 0";
        $activeCountSql = $countSql . " AND pausedUts = 0 AND inhqUts = 0 AND discontinuedUts = 0";
        $sql = "SELECT *, CONCAT(teamNumber, '-', ($countSql)) AS armNumber, ($countSql) AS startCount, ($activeCountSql) AS activeMemberCount FROM nathejk_team team WHERE team.id = :id AND team.deletedUts = 0";
        $row = $this->app['dbs']['monolith']->executeQuery($sql, ['id' => $teamId])->fetchObject();
        if (!$row) return null;
        $row->parentTeam = intval($row->parentTeamId) ? $this->findTeam($row->parentTeamId) : null;
        $row->catchCount = $this->findContactCount($teamId, true);
        $row->contactCount = $this->findContactCount($teamId);
        $row->title = utf8_decode($row->title);

        $activeCount = $row->activeMemberCount;
        $armNumbers = array();
        foreach ($this->findSubTeamsStat($row->id) as $stat) {
            $activeCount += $stat->activeMemberCount;
            $armNumbers[] = $stat->armNumber;
        }
 
        $row->noticeText = '';
        if (count($armNumbers)) {
            $row->noticeText = "Patruljen er slået sammen med " . implode(' og ', $armNumbers) . " - de skal være i alt $activeCount spejdere";
        } else if ($row->activeMemberCount != $row->startCount) {
            $row->noticeText = "Patruljen er reduceret til {$row->activeMemberCount} spejdere";
        }
        return $row;
    }

    /**
     * @return {id, phone, isBandit, team.typeName, team.title}
     */
    public function findMember($memberId)
    {
        $sql = "SELECT * FROM nathejk_member WHERE id = :id AND deletedUts = 0";
        $row = $this->app['dbs']['monolith']->executeQuery($sql, ['id' => $memberId])->fetchObject();
        if ($row) {
            $row->team = $this->findTeam($row->teamId);
            $row->isBandit = in_array($row->team->typeName, ['klan', 'lok']); 
            return $row;
        }
        $sql = "SELECT * FROM personnel WHERE userId = :id";
        $row = $this->app['db']->executeQuery($sql, ['id' => $memberId])->fetchObject();
        if (!$row) return null;
        $row->id = $row->userId;
        $row->isBandit = in_array($row->department, ['bhq', 'lok1', 'lok2', 'lok3', 'lok4', 'lok5']);
        $row->team = [
            'typeName' => $row->department,
            'title' => $row->department,
        ];
        return $row;
    }

    public function findMembersByPhone($phone)
    {
        $sql = "SELECT * FROM nathejk_member WHERE phone = :phone AND deletedUts = 0";
        $stmt = $this->app['dbs']['monolith']->executeQuery($sql, ['phone' => $phone]);
        $members = [];
        while ($member = $stmt->fetchObject()) {
            $member->team = $this->findTeam($member->teamId);
            $member->isBandit = in_array($member->team->typeName, ['klan', 'lok']); 
            $members[$member->id] = $member;
        }
        if (count($members) > 0) {
            return $members;
        }
        $sql = "SELECT * FROM personnel WHERE phone = :phone";
        $stmt = $this->app['db']->executeQuery($sql, ['phone' => $phone]);
        $member = $stmt->fetchObject();
        if (!$member) {
            return [];
        }

        $member->id = $member->userId;
        $member->isBandit = in_array($member->department, ['bhq', 'lok1', 'lok2', 'lok3', 'lok4', 'lok5']);
        $member->team = [
            'typeName' => $member->department,
            'title' => $member->department,
        ];
        return[$member->userId => $member];
    }

    private function findContactCount($teamId, $onlyBandit = false)
    {
        $sql = "SELECT COUNT(*) AS contactCount FROM nathejk_checkIn WHERE teamId = :teamId AND typeName != 'qr-fail'";
        if ($onlyBandit) {
            $sql .= " AND isCaught = 1";
        }
        $row = $this->app['dbs']['monolith']->executeQuery($sql, ['teamId' => $teamId])->fetchObject();
        return $row ? $row->contactCount : 0;
    }

    private function findSubTeams($teamId)
    {
        $sql = "SELECT id FROM nathejk_team WHERE parentTeamId = :teamId";
        $stmt = $this->app['dbs']['monolith']->executeQuery($sql, ['teamId' => $teamId]);
        $teams = [];
        while ($team = $stmt->fetchObject()) {
            $teams[] = $this->findTeam($team->id);
        }
        return $teams;
    }

    /**
     * @return {activeMemberCount, armNumber}
     */
    private function findSubTeamsStat($teamId)
    {
        $countSql = "SELECT COUNT(*) FROM nathejk_member WHERE teamId = nathejk_team.id AND deletedUts = 0";
        $activeCountSql = $countSql . " AND pausedUts = 0 AND discontinuedUts = 0";
        $sql = "SELECT CONCAT(teamNumber, '-', ($countSql)) AS armNumber, ($activeCountSql) AS activeMemberCount FROM nathejk_team WHERE parentTeamId = :teamId";
        //$sql = "SELECT  FROM nathejk_team WHERE parentTeamId = :teamId";
        $stmt = $this->app['dbs']['monolith']->executeQuery($sql, ['teamId' => $teamId]);
        $teams = [];
        while ($team = $stmt->fetchObject()) {
            $teams[] = $team;
        }
        return $teams;
    }

    public function saveScan($team, $member, $loc)
    {
        $sql = "INSERT INTO nathejk_checkIn (teamId, memberId, location, createdUts, typeName, isCaught, outUts, deletedUts, remark) VALUES (?, ?, ?, UNIX_TIMESTAMP(NOW()), 'qr', ?, 0, 0, '')";
        $teams = array_merge($this->findSubTeams($team->id), [$team]);
        foreach ($teams as $t) {
            $this->app['dbs']['monolith']->executeQuery($sql, [$t->id, intval($member->id), $loc, (int)$member->isBandit]);
        }
    }
    public function finish($team)
    {
        $sql = "UPDATE nathejk_team SET finishUts=:uts WHERE id=:teamId AND finishUts=0";
        $teams = array_merge($this->findSubTeams($team->id), [$team]);
        foreach ($teams as $t) {
            $this->app['dbs']['monolith']->executeQuery($sql, ['uts' => time(), 'teamId' => $t->id]);
        }
    }
/*
    public function getNoticeText()
    {
        if ($this->teams) {
            $activeCount = $this->activeMemberCount;
            $armNumbers = array();
            foreach ($this->teams as $team) {
                $activeCount += $team->activeMemberCount;
                $armNumbers[] = $team->armNumber;
            }
            return "Patruljen er slået sammen med " . implode(' og ', $armNumbers) . " - de skal være i alt $activeCount spejdere";
        } else if ($this->activeMemberCount != $this->startMemberCount) {
            return "Patruljen er reduceret til {$this->activeMemberCount} spejdere";
        }
        return '';
    }
    public function getArmNumber()
    {
        if (intval($this->teamNumber) > 0) {
            return "{$this->teamNumber}-{$this->startMemberCount}";
        }
        return '';
    }
*/
}
