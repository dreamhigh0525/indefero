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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
Pluf::loadFunction('Pluf_Shortcuts_RenderToResponse');
Pluf::loadFunction('Pluf_Shortcuts_GetObjectOr404');
Pluf::loadFunction('Pluf_Shortcuts_GetFormForModel');

/**
 * View git repository.
 */
class IDF_Views_Source
{
    public $changeLog_precond = array('IDF_Precondition::accessSource');
    public function changeLog($request, $match)
    {
        $title = sprintf(__('%1$s %2$s Change Log'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request);
        $branches = $scm->getBranches();
        $commit = $match[2];
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::changeLog',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $res = new Pluf_Template_ContextVars($scm->getChangeLog($commit, 25));
        $scmConf = $request->conf->getVal('scm', 'git');
        return Pluf_Shortcuts_RenderToResponse('source/changelog.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'changes' => $res,
                                                     'commit' => $commit,
                                                     'branches' => $branches,
                                                     'scm' => $scmConf,
                                                     ),
                                               $request);
    }

    public $treeBase_precond = array('IDF_Precondition::accessSource');
    public function treeBase($request, $match)
    {
        $title = sprintf(__('%1$s %2$s Source Tree'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request);
        $commit = $match[2];
        $branches = $scm->getBranches();
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }

        $res = new Pluf_Template_ContextVars($scm->filesAtCommit($commit));
        $cobject = $scm->getCommit($commit);
        $tree_in = in_array($commit, $branches);
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = null;
        if ($scmConf === 'svn') {
            $props = $scm->getProperties($commit);
        }
        return Pluf_Shortcuts_RenderToResponse('source/'.$scmConf.'/tree.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'files' => $res,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'tree_in' => $tree_in,
                                                     'branches' => $branches,
                                                     'props' => $props,
                                                     ),
                                               $request);
    }

    public $tree_precond = array('IDF_Precondition::accessSource');
    public function tree($request, $match)
    {
        $title = sprintf(__('%1$s %2$s Source Tree'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request);
        $branches = $scm->getBranches();
        $commit = $match[2];
        $request_file = $match[3];
        if ('commit' != $scm->testHash($commit, $request_file)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $request_file_info = $scm->getFileInfo($request_file, $commit);
        if (!$request_file_info) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        if ($request_file_info->type != 'tree') {
            $info = self::getMimeType($request_file_info->file);
            $rep = new Pluf_HTTP_Response($scm->getBlob($request_file_info, $commit),
                                          $info[0]);
            $rep->headers['Content-Disposition'] = 'attachment; filename="'.$info[1].'"';
            return $rep;
        }
        $bc = self::makeBreadCrumb($request->project, $commit, $request_file_info->file);
        $page_title = $bc.' - '.$title;
        $cobject = $scm->getCommit($commit);
        $tree_in = in_array($commit, $branches);
        $res = new Pluf_Template_ContextVars($scm->filesAtCommit($commit, $request_file));
        // try to find the previous level if it exists.
        $prev = split('/', $request_file);
        $l = array_pop($prev);
        $previous = substr($request_file, 0, -strlen($l.' '));
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = null;
        if ($scmConf === 'svn') {
            $props = $scm->getProperties($commit, $request_file);
        }
        return Pluf_Shortcuts_RenderToResponse('source/'.$scmConf.'/tree.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'breadcrumb' => $bc,
                                                     'files' => $res,
                                                     'commit' => $commit,
                                                     'cobject' => $cobject,
                                                     'base' => $request_file_info->file,
                                                     'prev' => $previous,
                                                     'tree_in' => $tree_in,
                                                     'branches' => $branches,
                                                     'props' => $props,
                                                     ),
                                               $request);
    }

    public static function makeBreadCrumb($project, $commit, $file, $sep='/')
    {
        $elts = split('/', $file);
        $out = array();
        $stack = '';
        $i = 0;
        foreach ($elts as $elt) {
            $stack .= ($i==0) ? $elt : '/'.$elt;
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::tree',
                                            array($project->shortname,
                                                  $commit, $stack));
            $out[] = '<a href="'.$url.'">'.Pluf_esc($elt).'</a>';
            $i++;
        }
        return '<span class="breadcrumb">'.implode('<span class="sep">'.$sep.'</span>', $out).'</span>';
    }

    public $commit_precond = array('IDF_Precondition::accessSource');
    public function commit($request, $match)
    {
        $scm = IDF_Scm::get($request);
        $commit = $match[2];
        $branches = $scm->getBranches();
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $title = sprintf(__('%s Commit Details'), (string) $request->project);
        $page_title = sprintf(__('%s Commit Details - %s'), (string) $request->project, $commit);
        $cobject = $scm->getCommit($commit);
        $diff = new IDF_Diff($cobject->changes);
        $diff->parse();
        $scmConf = $request->conf->getVal('scm', 'git');
        return Pluf_Shortcuts_RenderToResponse('source/commit.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'diff' => $diff,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'branches' => $branches,
                                                     'scm' => $scmConf,
                                                     ),
                                               $request);
    }

    /**
     * Get a zip archive of the current commit.
     *
     */
    public $download_precond = array('IDF_Precondition::accessSource');
    public function download($request, $match)
    {
        $commit = trim($match[2]);
        $scm = IDF_Scm::get($request);
        $branches = $scm->getBranches();
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $base = $request->project->shortname.'-'.$commit;
        $cmd = $scm->getArchiveCommand($commit, $base.'/');
        $rep = new Pluf_HTTP_Response_CommandPassThru($cmd, 'application/x-zip');
        $rep->headers['Content-Transfer-Encoding'] = 'binary';
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$base.'.zip"';
        return $rep;
    }

    /**
     * Find the mime type of a file.
     *
     * Use /etc/mime.types to find the type.
     *
     * @param string Filename/Filepath
     * @param string Path to the mime types database ('/etc/mime.types')
     * @param array  Mime type found or 'application/octet-stream' and basename
     */
    public static function getMimeType($file, $src='/etc/mime.types')
    {
        $mimes = preg_split("/\015\012|\015|\012/", file_get_contents($src));
        $info = pathinfo($file);
        if (isset($info['extension'])) {
            foreach ($mimes as $mime) {
                if ('#' != substr($mime, 0, 1)) {
                    $elts = preg_split('/ |\t/', $mime, -1, PREG_SPLIT_NO_EMPTY);
                    if (in_array($info['extension'], $elts)) {
                        return array($elts[0], $info['basename']);
                    }
                }
            }
        } else {
            // we consider that if no extension and base name is all
            // uppercase, then we have a text file.
            if ($info['basename'] == strtoupper($info['basename'])) {
                return array('text/plain', $info['basename']);
            }
        }
        return array('application/octet-stream', $info['basename']);
    }

    /**
     * Get the scm type for page title
     *
     * @return String
     */
    private function getScmType($request)
    {
        return mb_convert_case($request->conf->getVal('scm', 'git'),
                               MB_CASE_TITLE, 'UTF-8');
    }
}

function IDF_Views_Source_PrettySize($size)
{
    return Pluf_Template::markSafe(Pluf_Utils::prettySize($size));
}

