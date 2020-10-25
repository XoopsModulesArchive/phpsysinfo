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
// $Id: class.bsd.common.inc.php,v 1.1 2006/03/27 01:35:48 mikhail Exp $
//

class bsd_common
{
    public $dmesg;

    // Our constructor

    // this function is run on the initialization of this class

    public function __construct()
    {
        // initialize all the variables we need from our parent class

        parent::__construct();
    }

    // read /var/run/dmesg.boot, but only if we haven't already.

    public function read_dmesg()
    {
        if (!$this->dmesg) {
            $this->dmesg = file('/var/run/dmesg.boot');
        }

        return $this->dmesg;
    }

    // grabs a key from sysctl(8)

    public function grab_key($key)
    {
        return execute_program('sysctl', "-n $key");
    }

    // get our apache SERVER_NAME or vhost

    public function hostname()
    {
        if (!($result = getenv('SERVER_NAME'))) {
            $result = 'N.A.';
        }

        return $result;
    }

    // get our canonical hostname

    public function chostname()
    {
        return execute_program('hostname');
    }

    // get the IP address of our canonical hostname

    public function ip_addr()
    {
        if (!($result = getenv('SERVER_ADDR'))) {
            $result = gethostbyname($this->chostname());
        }

        return $result;
    }

    public function kernel()
    {
        $s = $this->grab_key('kern.version');

        $a = explode(':', $s);

        return $a[0];
    }

    public function uptime()
    {
        $sys_ticks = $this->get_sys_ticks();

        $min = $sys_ticks / 60;

        $hours = $min / 60;

        $days = floor($hours / 24);

        $hours = floor($hours - ($days * 24));

        $min = floor($min - ($days * 60 * 24) - ($hours * 60));

        if (0 != $days) {
            $result = "$days days, ";
        }

        if (0 != $hours) {
            $result .= "$hours hours, ";
        }

        $result .= "$min minutes";

        return $result;
    }

    public function users()
    {
        return execute_program('who', '| wc -l');
    }

    public function loadavg()
    {
        $s = $this->grab_key('vm.loadavg');

        $s = preg_replace('{ ', '', $s);

        $s = preg_replace(' }', '', $s);

        $results = explode(' ', $s);

        return $results;
    }

    public function cpu_info()
    {
        $results = [];

        $ar_buf = [];

        $results['model'] = $this->grab_key('hw.model');

        $results['cpus'] = $this->grab_key('hw.ncpu');

        for ($i = 0, $iMax = count($this->read_dmesg()); $i < $iMax; $i++) {
            $buf = $this->dmesg[$i];

            if (preg_match("/$this->cpu_regexp/", $buf, $ar_buf)) {
                $results['mhz'] = round($ar_buf[2]);

                break;
            }
        }

        return $results;
    }

    // get the scsi device information out of dmesg

    public function scsi()
    {
        $results = [];

        $s = 0;

        for ($i = 0, $iMax = count($this->read_dmesg()); $i < $iMax; $i++) {
            $buf = $this->dmesg[$i];

            if (preg_match("/$this->scsi_regexp/", $buf, $ar_buf)) {
                $results[$s++] = $ar_buf[1] . ': ' . $ar_buf[2];
            }
        }

        return array_values(array_unique($results));
    }

    // get the pci device information out of dmesg

    public function pci()
    {
        $results = [];

        $s = 0;

        for ($i = 0, $iMax = count($this->read_dmesg()); $i < $iMax; $i++) {
            $buf = $this->dmesg[$i];

            if (preg_match('/(.*): <(.*)>(.*) pci[0-9]$/', $buf, $ar_buf)) {
                $results[$s++] = $ar_buf[1] . ': ' . $ar_buf[2];
            } elseif (preg_match('/(.*): <(.*)>.* at [.0-9]+ irq/', $buf, $ar_buf)) {
                $results[$s++] = $ar_buf[1] . ': ' . $ar_buf[2];
            }
        }

        sort($results);

        return array_values(array_unique($results));
    }

    // get the ide device information out of dmesg

    public function ide()
    {
        $results = [];

        $s = 0;

        for ($i = 0, $iMax = count($this->read_dmesg()); $i < $iMax; $i++) {
            $buf = $this->dmesg[$i];

            if (preg_match('/^(ad[0-9]): (.*)MB <(.*)> (.*) (.*)/', $buf, $ar_buf)) {
                $s = $ar_buf[1];

                $results[$s]['model'] = $ar_buf[3];

                $results[$s]['media'] = 'Hard Disk';

                $results[$s]['capacity'] = $ar_buf[2] * 2048 * 1.049;
            } else {
                if (preg_match('/^(acd[0-9]): (.*) <(.*)> (.*)/', $buf, $ar_buf)) {
                    $s = $ar_buf[1];

                    $results[$s]['model'] = $ar_buf[3];

                    $results[$s]['media'] = 'CD-ROM';
                }
            }
        }

        //return array_values(array_unique($results));

        // 1. more useful to have device names

        // 2. php 4.1.1 array_unique() deletes non-unique values.

        return $results;
    }

    public function memory()
    {
        $s = $this->grab_key('hw.physmem');

        if (PHP_OS == 'FreeBSD' || PHP_OS == 'OpenBSD') {
            # vmstat on fbsd 4.4 or greater outputs kbytes not hw.pagesize

            # I should probably add some version checking here, but for now

            # we only support fbsd 4.4

            $pagesize = 1024;
        } else {
            $pagesize = $this->grab_key('hw.pagesize');
        }

        $results['ram'] = [];

        $pstat = execute_program('vmstat');

        $lines = preg_split("\n", $pstat);

        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $ar_buf = preg_preg_split("/\s+/", $lines[$i], 19);

            if (2 == $i) {
                $results['ram']['free'] = $ar_buf[5] * $pagesize / 1024;
            }
        }

        $results['ram']['total'] = $s / 1024;

        $results['ram']['shared'] = 0;

        $results['ram']['buffers'] = 0;

        $results['ram']['used'] = $results['ram']['total'] - $results['ram']['free'];

        $results['ram']['cached'] = 0;

        $results['ram']['t_used'] = $results['ram']['used'];

        $results['ram']['t_free'] = $results['ram']['free'];

        $results['ram']['percent'] = round(($results['ram']['used'] * 100) / $results['ram']['total']);

        if (PHP_OS == 'OpenBSD') {
            $pstat = execute_program('swapctl', '-l -k');
        } else {
            $pstat = execute_program('swapinfo', '-k');
        }

        $lines = preg_split("\n", $pstat);

        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $ar_buf = preg_preg_split("/\s+/", $lines[$i], 6);

            if (0 == $i) {
                $results['swap']['total'] = 0;

                $results['swap']['used'] = 0;

                $results['swap']['free'] = 0;
            } else {
                $results['swap']['total'] += $ar_buf[1];

                $results['swap']['used'] += $ar_buf[2];

                $results['swap']['free'] += $ar_buf[3];
            }
        }

        $results['swap']['percent'] = round(($results['swap']['used'] * 100) / $results['swap']['total']);

        return $results;
    }

    public function filesystems()
    {
        $df = execute_program('df', '-k');

        $mounts = preg_split("\n", $df);

        $fstype = [];

        $s = execute_program('mount');

        $lines = explode("\n", $s);

        $i = 0;

        while (list(, $line) = each($lines)) {
            preg_match('(.*) \((.*)\)', $line, $a);

            $m = explode(' ', $a[0]);

            $fsdev[$m[0]] = $a[2];
        }

        for ($i = 1, $j = 0, $iMax = count($mounts); $i < $iMax; $i++) {
            $ar_buf = preg_preg_split("/\s+/", $mounts[$i], 6);

            // skip the proc filesystem

            if ('procfs' == $ar_buf[0] || 'linprocfs' == $ar_buf[0]) {
                continue;
            }

            $results[$j] = [];

            $results[$j]['disk'] = $ar_buf[0];

            $results[$j]['size'] = $ar_buf[1];

            $results[$j]['used'] = $ar_buf[2];

            $results[$j]['free'] = $ar_buf[3];

            $results[$j]['percent'] = $ar_buf[4];

            $results[$j]['mount'] = $ar_buf[5];

            ($fstype[$ar_buf[5]]) ? $results[$j]['fstype'] = $fstype[$ar_buf[5]] : $results[$j]['fstype'] = $fsdev[$ar_buf[0]];

            $j++;
        }

        return $results;
    }
}
