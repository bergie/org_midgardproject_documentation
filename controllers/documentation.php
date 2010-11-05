<?php
/**
 * @package org_midgardproject_documentation
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard documentation display controller
 *
 * @package org_midgardproject_documentation
 */
class org_midgardproject_documentation_controllers_documentation
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    private function prepare_component($component)
    {
        $this->data['component'] = $component;
        try
        {
            $this->component = midgardmvc_core::get_instance()->component->get($this->data['component']);
        }
        catch (Exception $e)
        {
            throw new midgardmvc_exception_notfound("Component {$this->data['component']} not found");
        }
    }

    private function list_directory($component, $path, $prefix = '')
    {
        $files = array
        (
            'name'    => basename($path),
            'label'   => ucfirst(str_replace('_', ' ', basename($path))),
            'folders' => array(),
            'files'   => array(),
        );

        if (!file_exists($path))
        {
            return $files;
        }

        $directory = dir($path);
        while (false !== ($entry = $directory->read()))
        {
            if (substr($entry, 0, 1) == '.')
            {
                // Ignore dotfiles
                continue;
            }

            if (is_dir("{$path}/{$entry}"))
            {
                // List subdirectory
                $files['folders'][$entry] = $this->list_directory($component, "{$path}/{$entry}", "{$prefix}{$entry}/");
                continue;
            }
            
            $pathinfo = pathinfo("{$path}/{$entry}");
            
            if (   !isset($pathinfo['extension'])
                || $pathinfo['extension'] != 'markdown')
            {
                // We're only interested in Markdown files
                continue;
            };
            $files['files'][] = array
            (
                'label' => ucfirst(str_replace('_', ' ', $pathinfo['filename'])),
                'url' => midgardmvc_core::get_instance()->dispatcher->generate_url('omd_show', array('variable_arguments' => explode('/', "{$component}/{$prefix}{$pathinfo['filename']}")), $this->request),
                'path' => "{$prefix}{$pathinfo['filename']}/",
            );
        }
        $directory->close();
        return $files;
    }

    public function get_index(array $args)
    {
        $components = midgardmvc_core::get_instance()->component->get_components();
        $this->data['components'] = array();
        foreach ($components as $component)
        {
            $component_info = array();
            $component_info['name'] = $component->name;
            $component_info['url'] = midgardmvc_core::get_instance()->dispatcher->generate_url('omd_component', array('component' => $component->name), $this->request);
            $this->data['components'][] = $component_info;
        }
    }

    public function get_component(array $args)
    {
        $this->prepare_component($args['component'], $this->data);

        $this->data['description'] = $this->component->get_description();

        $this->data['files'] = $this->list_directory($this->data['component'], MIDGARDMVC_ROOT . "/{$this->data['component']}/documentation");
    }

    public function get_show(array $args)
    {
        $this->data['component'] = array_shift($args['variable_arguments']);
        $this->prepare_component($this->data['component'], $this->data);
        $path = MIDGARDMVC_ROOT . "/{$this->data['component']}/documentation";
        foreach ($args['variable_arguments'] as $key => $argument)
        {
            if ($argument == '..')
            {
                continue;
            }
            
            $path .= "/{$argument}";
        }

        if (   file_exists($path)
            && !is_dir($path))
        {
            // Image or other non-Markdown doc file, pass directly
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mimetype = 'application/octet-stream';
            switch ($extension)
            {
                case 'png':
                    $mimetype = 'image/png';
                    break;
            }
            midgardmvc_core::get_instance()->dispatcher->header("Content-type: {$mimetype}");
            readfile($path);
            die();
        }

        $path .= '.markdown';
        if (!file_exists($path))
        {
            throw new midgardmvc_exception_notfound("File not found");
        }

        require_once MIDGARDMVC_ROOT .'/midgardmvc_core/helpers/markdown.php';
        $this->data['markdown'] = file_get_contents($path);
        $this->data['markdown_formatted'] = Markdown($this->data['markdown']);
    }
}
?>
