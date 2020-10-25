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
// $Id: class.darwin.inc.php,v 1.1 2006/03/27 01:35:48 mikhail Exp $
//

require __DIR__ . '/includes/os/class.BSD.common.inc.php';

echo "<p align=center><b>Note: The Darwin version of phpSysInfo is work in progress, some things currently don't work</b></p>";

class sysinfo extends bsd_common
{
    public $cpu_regexp;

    public $scsi_regexp;

    // Our contstructor

    // this function is run on the initialization of this class

    public function __construct()
    {
        //$this->cpu_regexp = "CPU: (.*) \((.*)-MHz (.*)\)";
        //$this->scsi_regexp = "^(.*): <(.*)> .*SCSI.*device";
    }

    public function grab_key($key)
    {
        $s = execute_program('sysctl', $key);

        $s = preg_replace($key . ': ', '', $s);

        $s = preg_replace($key . ' = ', '', $s); // fix Apple set keys

        return $s;
    }

    public function grab_ioreg($key)
    {
        $s = execute_program('ioreg', '-cls "$key" | grep "$key"'); //ioreg -cls "$key" | grep "$key"

        $s = preg_replace($key . ': ', '', $s);

        $s = preg_replace($key . ' = ', '', $s); // fix Apple set keys

        return $s;
    }

    public function get_sys_ticks()
    {
        $s = explode(' ', $this->grab_key('kern.boottime'));

        $a = strtotime("$s[2] $s[1] $s[4] $s[3]"); // convert boottime to proper value

        $sys_ticks = time() - $a;

        return $sys_ticks;
    }

    public function cpu_info()
    {
        $results = [];

        //$results['model'] = $this->grab_key('hw.model'); // need to expand this somehow...
        //$results['model'] = $this->grab_key('hw.machine');
        $results['model'] = preg_replace('Processor type: ', '', execute_program('hostinfo', '| grep "Processor type"')); // get processor type
        $results['cpus'] = $this->grab_key('hw.ncpu');

        $results['mhz'] = round($this->grab_key('hw.cpufrequency') / 1000000); // return cpu speed
        $results['cache'] = round($this->grab_key('hw.l2cachesize') / 1024); // return l2 cache

        return $results;
    }

    // get the pci device information out of ioreg

    public function pci()
    {
        $results = [];

        $s = execute_program('ioreg', '-cls "IOPCIDevice" | grep "IOPCIDevice"');

        $s = preg_replace('\|', '', $s);

        $s = preg_replace('\+\-\o', '', $s);

        $s = preg_replace('[ ]+', '', $s);

        $s = preg_replace('<classIOPCIDevice>', '', $s);

        $lines = preg_split("\n", $s);

        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $ar_buf = preg_preg_split("/\s+/", $lines[$i], 19);

            $results[$i] = $ar_buf[0];
        }

        sort($results);

        return array_values(array_unique($results));
    }

    // get the ide device information out of ioreg

    public function ide()
    {
        $results = [];

        // ioreg | grep "Media  <class IOMedia>"
        $s = execute_program('ioreg', '-cls "IOATABlockStorageDevice"'); //  | grep "IOATABlockStorageDevice"
        $s = preg_replace('\|[ ]+', '', $s);

        $s = preg_replace('\+\-\o', '', $s);

        $s = preg_replace('<', '//', $s);

        $s = preg_replace('>', '', $s);

        $lines = preg_split("\n", $s);

        $j = 0;

        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $ar_buf = preg_preg_split("/\/\//", $lines[$i], 19);

            if ('class IOMedia' == $ar_buf[1] && false !== strpos($ar_buf[0], "Media")) {
                // echo "$j: ";

                // echo $ar_buf[0];

                // echo "&nbsp;&nbsp;&nbsp;&nbsp;<b>";

                // echo $ar_buf[1];

                // echo "</b><br>\n";

                $results[$j++]['model'] = $ar_buf[0];
            }
        }

        return array_values(array_unique($results));
    }

    public function memory()
    {
        $s = $this->grab_key('hw.physmem');

        $results['ram'] = [];

        $pstat = execute_program('vm_stat'); // use darwin's vm_stat

        $lines = preg_split("\n", $pstat);

        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $ar_buf = preg_preg_split("/\s+/", $lines[$i], 19);

            if (1 == $i) {
                $results['ram']['free'] = $ar_buf[2] * 4; // calculate free memory from page sizes (each page = 4MB)
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

        // need to fix the swap info...

        $pstat = execute_program('swapinfo', '-k');

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

    public function network()
    {
        $netstat = execute_program('netstat', '-nbdi | cut -c1-24,42- | grep Link');

        $lines = preg_split("\n", $netstat);

        $results = [];

        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $ar_buf = preg_preg_split("/\s+/", $lines[$i]);

            if (!empty($ar_buf[0])) {
                $results[$ar_buf[0]] = [];

                $results[$ar_buf[0]]['rx_bytes'] = $ar_buf[5];

                $results[$ar_buf[0]]['rx_packets'] = $ar_buf[3];

                $results[$ar_buf[0]]['rx_errs'] = $ar_buf[4];

                $results[$ar_buf[0]]['rx_drop'] = $ar_buf[10];

                $results[$ar_buf[0]]['tx_bytes'] = $ar_buf[8];

                $results[$ar_buf[0]]['tx_packets'] = $ar_buf[6];

                $results[$ar_buf[0]]['tx_errs'] = $ar_buf[7];

                $results[$ar_buf[0]]['tx_drop'] = $ar_buf[10];

                $results[$ar_buf[0]]['errs'] = $ar_buf[4] + $ar_buf[7];

                $results[$ar_buf[0]]['drop'] = $ar_buf[10];
            }
        }

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

            switch ($ar_buf[0]) {
                case 'automount':   // skip the automount entries
                case 'devfs':       // skip the dev filesystem
                case 'fdesc':       // skip the fdesc
                case 'procfs':      // skip the proc filesystem
                case '<volfs>':     // skip the vol filesystem
                    continue 2;
                    break;
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
