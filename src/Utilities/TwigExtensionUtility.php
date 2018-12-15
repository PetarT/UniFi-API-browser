<?php

namespace WingWifi\Utilities;

class TwigExtensionUtility extends \Twig_Extension
{
    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return array(
            new \Twig_Filter('parseElapsedTime', function($time) {
                $hours   = $time / 60;
                $minutes = $time % 60;
                $days    = 0;
                $res     = array();

                if ($hours >= 24) {
                    $days  = $hours / 24;
                    $hours = $hours % 24;
                }

                if ($days > 1) {
                    $res[] = $days . ' dana';
                } elseif ($days == 1) {
                    $res[] = '1 dan';
                }

                if ($hours > 3) {
                    $res[] = $hours . ' sati';
                } elseif ($hours > 1) {
                    $res[] = $hours . ' sata';
                } elseif ($hours == 1) {
                    $res[] = '1 sat';
                }

                if ($minutes > 1) {
                    $res[] = $minutes . ' minuta';
                } elseif ($minutes == 1) {
                    $res[] = '1 minut';
                }

                return implode(', ', $res);
            }),
        );
    }
}
