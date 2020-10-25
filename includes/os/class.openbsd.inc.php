<?php

//
// phpSysInfo - A PHP System Information Script
// http://phpsysinfo.sourceforge.net/
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
// $Id: class.openbsd.inc.php,v 1.1 2006/03/27 01:35:48 mikhail Exp $
//

echo "<center><b>Note: The Net/OpenBSD version of phpSysInfo is work in progress, some things currently don't work</b></center>";

require __DIR__ . '/includes/os/class.BSD.common.inc.php';

class sysinfo extends bsd_common
{
    public $cpu_regexp;

    public $scsi_regexp;

    // Our contstructor

    // this function is run on the initialization of this class

    public function __construct()
    {
        $this->cpu_regexp = '^cpu(.*) (.*) MHz';

        $this->scsi_regexp = '^(.*) at scsibus.*: <(.*)> .*';
    }

    public function get_sys_ticks()
    {
        $a = $this->grab_key('kern.boottime');

        $sys_ticks = time() - $a;

        return $sys_ticks;
    }

    // get the pci device information out of dmesg

    public function pci()
    {
        $results = [];

        for ($i = 0, $iMax = count($this->read_dmesg()); $i < $iMax; $i++) {
            $buf = $this->dmesg[$i];

            if (preg_match('/(.*) at pci[0-9] (.*) "(.*)" (.*)$/', $buf, $ar_buf)) {
                $results[$i] = $ar_buf[1] . ': ' . $ar_buf[3];
            } elseif (preg_match('/"(.*)" (.*).* at [.0-9]+ irq/', $buf, $ar_buf)) {
                $results[$i] = $ar_buf[1] . ': ' . $ar_buf[2];
            }

            sort($results);
        }

        return $results;
    }

    public function network()
    {
        $netstat_b = execute_program('netstat', '-nbdi | cut -c1-25,44- | grep Link | grep -v \'* \'');

        $netstat_n = execute_program('netstat', '-ndi | cut -c1-25,44- | grep Link | grep -v \'* \'');

        $lines_b = preg_split("\n", $netstat_b);

        $lines_n = preg_split("\n", $netstat_n);

        $results = [];

        for ($i = 0, $iMax = count($lines_b); $i < $iMax; $i++) {
            $ar_buf_b = preg_preg_split("/\s+/", $lines_b[$i]);

            $ar_buf_n = preg_preg_split("/\s+/", $lines_n[$i]);

            if (!empty($ar_buf_b[0]) && !empty($ar_buf_n[3])) {
                $results[$ar_buf_b[0]] = [];

                $results[$ar_buf_b[0]]['rx_bytes'] = $ar_buf_b[3];

                $results[$ar_buf_b[0]]['rx_packets'] = $ar_buf_n[3];

                $results[$ar_buf_b[0]]['rx_errs'] = $ar_buf_n[4];

                $results[$ar_buf_b[0]]['rx_drop'] = $ar_buf_n[8];

                $results[$ar_buf_b[0]]['tx_bytes'] = $ar_buf_b[4];

                $results[$ar_buf_b[0]]['tx_packets'] = $ar_buf_n[5];

                $results[$ar_buf_b[0]]['tx_errs'] = $ar_buf_n[6];

                $results[$ar_buf_b[0]]['tx_drop'] = $ar_buf_n[8];

                $results[$ar_buf_b[0]]['errs'] = $ar_buf_n[4] + $ar_buf_n[6];

                $results[$ar_buf_b[0]]['drop'] = $ar_buf_n[8];
            }
        }

        return $results;
    }

    // get the ide device information out of dmesg

    public function ide()
    {
        $results = [];

        $s = 0;

        for ($i = 0, $iMax = count($this->read_dmesg()); $i < $iMax; $i++) {
            $buf = $this->dmesg[$i];

            if (preg_match('/^(.*) at pciide[0-9] (.*): <(.*)>/', $buf, $ar_buf)) {
                $s = $ar_buf[1];

                $results[$s]['model'] = $ar_buf[3];

                $results[$s]['media'] = 'Hard Disk';

                // now loop again and find the capacity

                for ($j = 0, $jMax = count($this->read_dmesg()); $j < $jMax; $j++) {
                    $buf_n = $this->dmesg[$j];

                    if (preg_match("/^($s): (.*), (.*), (.*)MB, .*$/", $buf_n, $ar_buf_n)) {
                        $results[$s]['capacity'] = $ar_buf_n[4] * 2048 * 1.049;
                    }
                }
            }
        }

        return $results;
    }
}
