<?php
namespace Nathejk\Scan;

class Repository
{
    protected $app;

    public function __construct(Application $app)
    {   
        $this->app = $app;
    }

    public function findTeam($teamId)
    {
        $countSql = "SELECT COUNT(*) FROM nathejk_member WHERE teamId = team.id AND deletedUts = 0";
        $activeCountSql = $countSql . " AND pausedUts = 0 AND discontinuedUts = 0";
        $sql = "SELECT *, CONCAT(teamNumber, '-', ($countSql)) AS armNumber, ($activeCountSql) AS activeMemberCount FROM nathejk_team team WHERE team.id = :id AND team.deletedUts = 0";
        $row = $this->app['db']->executeQuery($sql, ['id' => $teamId])->fetchObject();
        $row->parentTeam = intval($row->parentTeamId) ? $this->findTeam($row->parentTeamId) : null;
        $row->catchCount = $this->findContactCount($teamId, true);
        $row->contactCount = $this->findContactCount($teamId);
        $row->noticeText = 'Skal fanges';
        return $row ? $row : null;
    }

    public function findMember($memberId)
    {
        $sql = "SELECT * FROM nathejk_member WHERE id = :id AND deletedUts = 0";
        $row = $this->app['db']->executeQuery($sql, ['id' => $memberId])->fetchObject();
        $row->team = $this->findTeam($row->teamId);
        $row->isBandit = in_array($row->team->typeName, ['klan', 'lok']); 
        return $row ? $row : null;
    }

    public function findMembersByPhone($phone)
    {
        $sql = "SELECT * FROM nathejk_member WHERE phone = :phone AND deletedUts = 0";
        $stmt = $this->app['db']->executeQuery($sql, ['phone' => $phone]);
        $members = [];
        while ($member = $stmt->fetchObject()) {
            $members[$member->id] = $member;
        }
        return $members;
    }

    public function findContactCount($teamId, $onlyBandit = false)
    {
        $sql = "SELECT COUNT(*) AS contactCount FROM nathejk_checkIn WHERE teamId = :teamId AND typeName != 'qr-fail'";
        if ($onlyBandit) {
            $sql .= " AND isCaught = 1";
        }
        $row = $this->app['db']->executeQuery($sql, ['teamId' => $teamId])->fetchObject();
        return $row ? $row->contactCount : 0;
    }



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
}
