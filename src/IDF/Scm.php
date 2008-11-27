<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Manage differents SCM systems
 */
class IDF_Scm
{

    /**
     * Returns an instance of the correct scm backend object.
     *
     * @return Object
     */
    public static function get($request=null)
    {
        // Get scm type from project conf ; defaults to git
        $scm = $request->conf->getVal('scm', 'git');
        $scms = Pluf::f('allowed_scm');
        return call_user_func(array($scms[$scm], 'factory'),
                              $request->project);
    }

    /**
     * Equivalent to exec but with caching.
     *
     * @param string Command
     * @param &array Output
     * @param &int Return value
     * @return string Last line of the output
     */
    public static function exec($command, &$output=array(), &$return=0)
    {
        $key = md5($command);
        $cache = Pluf_Cache::factory();
        if (null === ($res=$cache->get($key))) {
            $ll = exec($command, $output, $return);
            if ($return != 0) {
                throw new IDF_Scm_Exception(sprintf('Error when running command: "%s", return code: %d', $command, $return));
            }
            $cache->set($key, array($ll, $return, $output));
        } else {
            list($ll, $return, $output) = $res;
        }
        return $ll;
    }

    /**
     * Equivalent to shell_exec but with caching.
     *
     * @param string Command
     * @return string Output of the command
     */
    public static function shell_exec($command)
    {
        $key = md5($command);
        $cache = Pluf_Cache::factory();
        if (null === ($res=$cache->get($key))) {
            $res = shell_exec($command);
            $cache->set($key, $res);
        } 
        return $res;
    }
}

