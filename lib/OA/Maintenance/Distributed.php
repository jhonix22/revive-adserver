<?php

/*
+---------------------------------------------------------------------------+
| Openads v${RELEASE_MAJOR_MINOR}                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
| For contact details, see: http://www.openads.org/                         |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

require_once MAX_PATH . '/lib/Max.php';

require_once MAX_PATH . '/lib/OA.php';
require_once MAX_PATH . '/lib/OA/DB/Distributed.php';
require_once MAX_PATH . '/lib/OA/DB/AdvisoryLock.php';
require_once MAX_PATH . '/lib/OA/DB/AdvisoryLock.php';
require_once MAX_PATH . '/lib/OA/ServiceLocator.php';
require_once MAX_PATH . '/lib/pear/Date.php';

/**
 * A library class for providing automatic maintenance process methods.
 *
 * @static
 * @package    OpenadsMaintenance
 * @author     Matteo Beccati <matteo.beccati@openx.org>
 */
class OA_Maintenance_Distributed
{
    /**
     * A method to run distributed maintenance.
     */
    function run()
    {
        if (empty($GLOBALS['_MAX']['CONF']['lb']['enabled'])) {
            OA::debug('Distributed stats disabled, not running Maintenance Distributed Engine', PEAR_LOG_INFO);
            return;
        }

        $oLock =& OA_DB_AdvisoryLock::factory();

        if ($oLock->get(OA_DB_ADVISORYLOCK_DISTIRBUTED))
        {
            OA::debug('Running Maintenance Distributed Engine', PEAR_LOG_INFO);

            // Attempt to increase PHP memory
            increaseMemoryLimit($GLOBALS['_MAX']['REQUIRED_MEMORY']['MAINTENANCE']);

            $oDbh      = OA_DB::singleton();
            $dbType    = strtolower($oDbh->dbsyntax);
            $fileName  = MAX_PATH . '/lib/OA/Dal/Maintenance/Distributed/'.$dbType.'.php';
            $className = "OA_Dal_Maintenance_Distributed_{$dbType}";

            require $fileName;

            $oDal = new $className();

            $oStart = $oDal->getMaintenanceDistributedLastRunInfo();

            if ($oStart) {
                // Ensure the the current time is registered with the OA_ServiceLocator
                $oServiceLocator =& OA_ServiceLocator::instance();
                $oEnd =& $oServiceLocator->get('now');
                if (!$oEnd) {
                    // Record the current time, and register with the OA_ServiceLocator
                    $oEnd = new Date();
                    $oServiceLocator->register('now', $oEnd);
                }

                // Copy statistics up to the previous second
                $oEnd->subtractSeconds(1);

                // Copy tables
                $oDal->processTables($oStart, $oEnd);

                $oDal->setMaintenanceDistributedLastRunInfo($oEnd);
            } else {
                OA::debug(' - No data to copy over', PEAR_LOG_INFO);
            }

            $oLock->release();

            OA::debug('Maintenance Distributed Engine Completed', PEAR_LOG_INFO);
        } else {
            OA::debug('Maintenance Distributed Engine Already Running', PEAR_LOG_INFO);
        }
    }
}

?>
