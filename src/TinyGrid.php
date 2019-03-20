<?php
namespace Com\DataGrid;

use Zend\Escaper;

use Zend\Paginator\Adapter\NullFill;
use Zend\Paginator\Adapter\AdapterInterface;
use Zend\Paginator\Adapter\DbSelect;
use Zend\Paginator\Adapter\ArrayAdapter;
use Zend\Paginator\Adapter\Iterator;
use Zend\Paginator\Paginator;

use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterInterface as dbAdapterInterface;
use Zend\Db\TableGateway\TableGateway;

use Zend\EventManager\EventManager;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManagerAwareInterface;

class TinyGrid implements EventManagerAwareInterface
{
    /**
     * @var string
     */
    protected  $basePath  = null;

    /**
     * @var array
     */
    protected $queryParams = array();

    /**
     * @var string
     */
    protected  $gridName = null;

    /**
     * @var Escaper\Escaper
     */
    protected  $escaper = null;

    /**
     * @var EventManagerInterface
     */
    protected  $eventManager = null;

    /**
     * @var int
     */
    protected  $defaultLimit = 25;

    /**
     * @var string
     */
    protected  $pageVarName = 'page';

    /**
     * @var string
     */
    protected  $sortVarName = 'sort';

    /**
     * @var string
     */
    protected  $orderVarName = 'order';

    /**
     * @var string
     */
    protected  $limitVarName = 'limit';

    /**
     * @var string
     */
    protected  $columns = array();

    /**
     * @var bool
     */
    protected $showPaginator = true;

    /**
     * @var bool
     */
    protected $showHeader = true;

    /**
     * @var bool
     */
    protected $paginatorPosition = 'bottom';

    /**
     * @var Zend\Paginator\Paginator
     */
    protected $paginator = null;

    /**
     * @var bool
     */
    protected $built = false;

    /**
     * @var Select|Iterator|array
     */
    protected $source = null;

    /**
     * @var dbAdapterInterface
     */
    protected $dbAdapter = null;

    /**
     * @var array
     */
    protected $paginatorConfig = array(

        # attributes used in the paginator
        'container' => array('class' => 'pagination-container pull-right'),
        'paginator' => array('class' => 'pagination'),
        'li' => array('class' => ''),
        'a' => array(),

        # classes
        'current_class' => 'active',
        'disabled_class' => 'disabled',

        # text used in the title attribute of links
        'titles' => array(
            'first' => 'First Page',
            'previous' => 'Previous Page',

            'next' => 'Next Page',
            'last' => 'Last Page',
        ),

        # labels used in the links 
        'labels' => array(
            'first' => '<span><<</span>',
            'previous' => '<span><</span>',

            'next' => '<span>></span>',
            'last' => '<span>>></span>',          
        ),

        # template used as info
        'template' => array(
            'info' => '<div class="">Showing records from {records_from} to {records_to} out of {records_total} <br> Page {page_current} out of {page_total}<br></div>'
        ),
    );

    /**
     * @var array
     */
    protected $headerAttr = array();

    /**
     * @var array
     */
    protected $mainContainerAttr = array(
        'class' => 'grid-panel',
    );

    /**
     * @var array
     */
    protected $tableContainerAttr = array(
        'class' => 'table-panel',
    );

    /**
     * @var array
     */
    protected $tableAttr = array(
        'class' => 'table table-bordered table-striped table-hover' #
        ,'cellspacing' => '0'
        ,'width' => '100%'
        ,'border' => '0'
    );


    
    /**
     * @param string $gridName
     * @param string $basePath
     * @param string $queryParams
     */   
    public function __construct($gridName = null, $basePath = null, array $queryParams = array())
    {
        $this->setBasePath($basePath);
        $this->setQueryParams($queryParams);
        $this->setGridName($gridName);
    }


    /**
     * @var string $basePath
     * @return TinyGrid
     */
    function setBasePath($basePath)
    {
        $this->basePath = $basePath;
        return $this;
    }

    /**
     * @return string
     */
    function getBasePath()
    {
        return $this->basePath;
    }


    /**
     * @param array $params
     * @return TinyGrid
     */
    function setQueryParams(array $queryParams)
    {
        $this->queryParams = $queryParams;
        return $this;
    }


    /**
     * @param string $key
     * @param string $value
     * @return TinyGrid
     */
    function setQueryParam($key, $value)
    {
        $this->queryParams[$key] = $value;
        return $this;
    }


    /**
     * @param string $key
     * @param string $def
     * @return mixed
     */
    function getQueryParam($key, $def = null)
    {
        $r = $def;
        if(isset($this->queryParams[$key]))
        {
            $r = $this->queryParams[$key];
        }

        return $r;
    }


    /**
     * @return array
     */
    function getQueryParams()
    {
        return $this->queryParams;
    }


    /**
     * @param string $gridName
     * @return TinyGrid
     */
    function setGridName($gridName)
    {
        $this->gridName = $gridName;
        return $this;
    }


    /**
     * @return string
     */
    function getGridName()
    {
        return $this->gridName;
    }


    /**
     * @param  Escaper\Escaper $escaper
     * @return TinyGrid
     */
    public function setEscaper(Escaper\Escaper $escaper)
    {
        $this->escaper  = $escaper;
        return $this;
    }

    /**
     * Get instance of Escaper
     *
     * @return null|Escaper\Escaper
     */
    public function getEscaper()
    {
        return $this->escaper;
    }


    /**
     * @param $eventManager EventManagerInterface
     * @return TinyGrid
     */
    function setEventManager(EventManagerInterface $eventManager)
    {
        $eventManager->addIdentifiers(array(
            get_called_class()
        ));
    
        $this->eventManager = $eventManager;
        
        # $this->getEventManager()->trigger('sendTweet', null, array('content' => $content));
        return $this;
    }


    /**
     * @return null | EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }


    /**
     * @param int $limit
     * @return TinyGrid
     */
    function setDefaultLimit($limit)
    {
        $this->defaultLimit = abs((int)$limit);
        return $this;
    }


    /**
     * @return int
     */
    function getDefaultLimit()
    {
        return $this->defaultLimit;
    }


    /**
     * @param string $val
     * @return TinyGrid
     */
    function setPageVarName($val)
    {
        if(empty($val))
        {
            throw new \Exception("Can't be mpty");
        }
            
        $this->pageVarName = $val;
        return $this;
    }

    
    /**
     * @return string
     */
    function getPageVarName()
    {
        return $this->pageVarName;
    }


    /**
     * @param string $val
     * @return TinyGrid
     */
    function setSortVarName($val)
    {
        if(empty($val))
        {
            throw new \Exception("Can't be mpty");
        }
            
        $this->sortVarName = $val;
        return $this;
    }

    
    /**
     * @return string
     */
    function getSortVarName()
    {
        return $this->sortVarName;
    }


    /**
     * @param string $val
     * @return TinyGrid
     */
    function setOrderVarName($val)
    {
        if(empty($val))
        {
            throw new \Exception("Can't be mpty");
        }
            
        $this->orderVarName = $val;
        return $this;
    }

    
    /**
     * @return string
     */
    function getOrderVarName()
    {
        return $this->orderVarName;
    }


    /**
     * @param string $val
     * @return TinyGrid
     */
    function setLimitVarName($val)
    {
        if(empty($val))
        {
            throw new \Exception("Can't be mpty");
        }
            
        $this->limitVarName = $val;
        return $this;
    }


    /**
     * @return string
     */
    function getLimitVarName()
    {
        return $this->limitVarName;
    }
    


    /**
     * @param array $columns
     * @return TinyGrid
     */
    function setColumns(array $columns = array())
    {
        $this->columns = $columns;
        return $this;
    }


    /**
     * @return array
     */
    function getColumns()
    {
        return $this->columns;
    }


    /**
     * @param bool $var
     * @return TinyGrid
     */
    function setShowPaginator($val)
    {
        $this->showPaginator = (bool)$val;
        return $this;
    }


    /**
     * @return bool
     */
    function getShowPaginator()
    {
        return $this->showPaginator;
    }


    /**
     * @param bool $var
     * @return TinyGrid
     */
    function setShowHeader($val)
    {
        $this->showHeader = (bool)$val;
        return $this;
    }


    /**
     * @return bool
     */
    function getShowHeader()
    {
        return $this->showHeader;
    }


    /**
     * @param string $val top|bottom|both|none
     * @return TinyGrid
     */
    function setPaginatorPosition($val)
    {
        $this->paginatorPosition = $val;
        return $this;
    }


    /**
     * @return string
     */
    function getPaginatorPosition()
    {
        return $this->paginatorPosition;
    }



    /**
     * @param AbstractTableGateway|Select|Iterator|array $source
     * @param dbAdapterInterface $dbAdapter
     * @return TinyGrid
     */
    function setSource($source, dbAdapterInterface $dbAdapter = null)
    {
        $this->paginator = null;
        $this->built = false;

        if($source instanceof Select)
        {
            if(!$dbAdapter instanceof dbAdapterInterface)
            {
                throw new \Exception('Missing $dbAdapter parameter.');
            }

            $this->dbAdapter = $dbAdapter;
        }
        elseif(is_array($source) || ($source instanceof \Iterator))
        {
            ;
        }
        elseif($source instanceof AbstractTableGateway)
        {
            if(!$dbAdapter instanceof dbAdapterInterface)
            {
                throw new \Exception('Missing $dbAdapter parameter.');
            }

            $this->dbAdapter = $source->getAdapter();
            $source = $source->getSql()->select();
        }
        else
        {
            throw new \Exception('$source parameter must be a valid instance of: AbstractTableGateway, Select, Iterator or array');
        }

        #
        $this->_setSource($source);

        return $this;
    }


    /**
     * @return Select|Iterator|array|null
     */
    function getSource()
    {
        return $this->source;
    }


    private function _setSource($source)
    {
        $this->source = $source;
    }


    /**
     * @return AdapterInterface
     */
    function getDbAdapter()
    {
        return $this->dbAdapter;
    }


    /**
     * @var array
     * @return TinyGrid
     */
    function setHeaderAttr(array $attr = array())
    {
        $this->headerAttr = $attr;
        return $this;
    }

    /**
     * @return array
     */
    function getHeaderAttr()
    {
        return $this->headerAttr;
    }

    /**
     * @var array
     * @return TinyGrid
     */
    function setMainContainerAttr(array $attr = array())
    {
        $this->mainContainerAttr = $attr;
        return $this;
    }

    /**
     * @return array
     */
    function getMainContainerAttr()
    {
        return $this->mainContainerAttr;
    }

    /**
     * @var array
     * @return TinyGrid
     */
    function setTableContainerAttr(array $attr = array())
    {
        $this->tableContainerAttr = $attr;
        return $this;
    }

    /**
     * @return array
     */
    function getTableContainerAttr()
    {
        return $this->tableContainerAttr;
    }



    /**
     * @var array
     * @return TinyGrid
     */
    function setTableAttr(array $attr = array())
    {
        $this->tableAttr = $attr;
        return $this;
    }

    /**
     * @return array
     */
    function getTableAttr()
    {
        return $this->tableAttr;
    }
    

    /**
     * @param array $config
     * @return TinyGrid
     */
    function setPaginatorConfig(array $config)
    {

        if(isset($config['container']) && is_array($config['container']))
        {
            $this->paginatorConfig['container'] = $config['container'];
        }

        if(isset($config['paginator']) && is_array($config['paginator']))
        {
            $this->paginatorConfig['paginator'] = $config['paginator'];
        }

        if(isset($config['li']) && is_array($config['li']))
        {
            $this->paginatorConfig['li'] = $config['li'];
        }

        if(isset($config['a']) && is_array($config['a']))
        {
            $this->paginatorConfig['a'] = $config['a'];
        }

        if(isset($config['current_class']))
        {
            $this->paginatorConfig['current_class'] = $config['current_class'];
        }

        if(isset($config['disabled_class']))
        {
            $this->paginatorConfig['disabled_class'] = $config['disabled_class'];
        }

        if(isset($config['titles']) && is_array($config['titles']))
        {
            $this->paginatorConfig['titles']['first'] = isset($config['titles']['first']) ? $config['titles']['first'] : null;
            $this->paginatorConfig['titles']['previous'] = isset($config['titles']['previous']) ? $config['titles']['previous'] : null;
            $this->paginatorConfig['titles']['next'] = isset($config['titles']['next']) ? $config['titles']['next'] : null;
            $this->paginatorConfig['titles']['last'] = isset($config['titles']['last']) ? $config['titles']['last'] : null;
        }

        if(isset($config['labels']) && is_array($config['labels']))
        {
            $this->paginatorConfig['labels']['first'] = isset($config['labels']['first']) ? $config['labels']['first'] : null;
            $this->paginatorConfig['labels']['previous'] = isset($config['labels']['previous']) ? $config['labels']['previous'] : null;
            $this->paginatorConfig['labels']['next'] = isset($config['labels']['next']) ? $config['labels']['next'] : null;
            $this->paginatorConfig['labels']['last'] = isset($config['labels']['last']) ? $config['labels']['last'] : null;
        }

        if(isset($config['template']) && is_array($config['template']))
        {
            $this->paginatorConfig['template']['info'] = isset($config['template']['info']) ? $config['template']['info'] : null;
        }

        return $this;
    }


    /**
     * @return array 
     */
    function getPaginatorConfig()
    {
        return $this->paginatorConfig;
    }


    /**
     * @return Zend\Paginator\Paginator
     */
    function getPaginator()
    {
        if(!$this->paginator instanceof Paginator)
        {
            $source = $this->getSource();
            if($source instanceof Select)
            {
                $adapter = new DbSelect($source, $this->getDbAdapter());
            }
            elseif($source instanceof \Iterator)
            {
                $adapter = new Iterator($source);
            }
            elseif(is_array($source))
            {
                $adapter = new ArrayAdapter($source);
            }
            else
            {
                $adapter = new NullFill();
            }

            $this->paginator = new Paginator($adapter);
        }
        
        return $this->paginator;
    }



    /**
     * @return TinyGrid
     */
    function build()
    {
        if(!$this->built)
        {
            $qParams = $this->getQueryParams();
            $gridName = $this->getGridName();

            $this->_buildColumns();
            $this->_buildDatasource();

            {
                $source = $this->getSource();
                $source = $this->_applySort($source);
                $source = $this->_applyFilter($source);

                $this->_setSource($source);
            }

            $paginator = $this->getPaginator();

            #
            $pageKey = "{$gridName}{$this->getPageVarName()}";
            $pageNumber = isset($qParams[$pageKey]) ? $qParams[$pageKey] : 1;
            $paginator->setCurrentPageNumber($pageNumber);

            #
            $pageKey = "{$gridName}{$this->getLimitVarName()}";

            $limitNumber = isset($qParams[$pageKey]) ? $qParams[$pageKey] : $this->getDefaultLimit();
            $paginator->setItemCountPerPage($limitNumber);

            $this->built = true;
        }
        
        return $this;
    }


    /**
     * Generates the html for the grid and return as string
     * @return  string
     */    
    public function render()
    {
        if(!$this->built)
        {
            $this->build();
        }

        #
        $paginator = $this->renderPaginator();
        $position = $this->getPaginatorPosition();

        #
        $html = '';
        $html .= sprintf('<div%s>', $this->_attrToStr($this->getMainContainerAttr()));

        $html .= (('top' == $position) || ('both' == $position)) ? $paginator : '';

        $html .= sprintf('<div%s>', $this->_attrToStr($this->getTableContainerAttr()));

        $html .= sprintf('<table%s>', $this->_attrToStr($this->getTableAttr()));
        $html .= $this->_renderHeader();
        $html .= $this->_renderHeaderFilter();
        $html .= $this->_renderRows();
        $html .= '</table>';

        $html .= '</div>';

        $html .= (('bottom' == $position) || ('both' == $position)) ? $paginator : '';

        $html .= '</div>';


        #
        $gridName = $this->getGridName();
        $qParams = $this->getQueryParams();

        $sParams = json_encode($qParams);

        #
        $gridSearchClass = $this->_getGridSearchClass();

        $html .= '<script type="text/javascript">';

        $html .= "
        function search_{$gridName}(val, name)
        {
            var s_params = $sParams;
            s_params[name] = val;

            var params = '';
            for(i in s_params)
            {
                var s = s_params[i];

                if('' == s)
                {
                    continue;
                }

                params += i + '=' + s + '&';
            }

            if('&' == params.slice(-1))
            {
                params = params.substring(0, params.length - 1);
            }

            var currPath = '{$this->getBasePath()}';

            if(params != '')
            {
                currPath += '?' + params;
            }

            location.href = currPath;
        };

        $('.{$gridSearchClass}').on('keyup', function(e){
            if(e.keyCode == 13)
            {
                search_{$gridName}($(this).val(), $(this).attr('name'));
            };
        });

        $('select.{$gridSearchClass}').on('change', function(e){
            search_{$gridName}($(this).val(), $(this).attr('name'));
        });
        ";

        $html .= '</script>';

        return $html;
    }



    /**
     * @return string
     */
    function renderPaginator()
    {
        $html = '';
        if($this->getShowPaginator())
        {
            #
            $config = $this->getPaginatorConfig();

            $paginator = $this->getPaginator();
            $pages = $paginator->getPages();

            # $itemsPerPage = $pages->itemCountPerPage;
            
            $html = '';
            $html .= sprintf('<div%s>', $this->_attrToStr($config['container']));         

            #
            $infoTemplate = '';
            if(isset($config['template']['info']))
            {
                if($config['template']['info'])
                {
                    $search = array(
                        '{records_from}' => $pages->firstItemNumber,
                        '{records_to}' => $pages->lastItemNumber,
                        '{records_total}' => $pages->totalItemCount,

                        '{page_current}' => $pages->current,
                        '{page_total}' => $pages->pageCount,
                    );

                    $infoTemplate = str_replace(array_keys($search), array_values($search), $config['template']['info']);
                }
            }


            $html .= $infoTemplate;
            $html .= sprintf('<ul%s>', $this->_attrToStr($config['paginator']));
            
            if(isset($pages->first) && ($pages->first != $pages->current))
            {
                $type = 'first';
                $current = 0;
                $active = 1;
                $html .= $this->_createLink($type, $current, $active);
            }
            else
            {
                $type = 'first';
                $current = 1;
                $active = 0;
                $html .= $this->_createLink($type, $current, $active);
            }


            if(isset($pages->previous))
            {
                $type = 'previous';
                $current = 0;
                $active = 1;
                $html .= $this->_createLink($type, $current, $active);
            }
            else
            {
                $type = 'previous';
                $current = 1;
                $active = 0;
                $html .= $this->_createLink($type, $current, $active);
            }

            if(isset($pages->pagesInRange))
            {
                foreach($pages->pagesInRange as $page)
                {
                    if($page == $pages->current)
                    {
                        $type = $page;
                        $current = 1;
                        $active = 0;
                        $html .= $this->_createLink($type, $current, $active);
                    }
                    else
                    {
                        $type = $page;
                        $current = 0;
                        $active = 1;
                        $html .= $this->_createLink($type, $current, $active);
                    }
                }
            }

            if(isset($pages->next))
            {
                $type = 'next';
                $current = 0;
                $active = 1;
                $html .= $this->_createLink($type, $current, $active);
            }
            else
            {
                $type = 'next';
                $current = 1;
                $active = 0;
                $html .= $this->_createLink($type, $current, $active);
            }

            if(isset($pages->last) && ($pages->last != $pages->current))
            {
                $type = 'last';
                $current = 0;
                $active = 1;
                $html .= $this->_createLink($type, $current, $active);
            }
            else
            {
                $type = 'last';
                $current = 1;
                $active = 0;
                $html .= $this->_createLink($type, $current, $active);
            }

            $html .= '</ul>';
            $html .= '</div>';
        }

        return $html;
    }



    /**
     * @return  string
     */    
    protected  function _renderRows()
    {
        $html = '';
        $rowset = (array)$this->getPaginator()->getCurrentItems();

        $eventParams = array(
            'rowset' => $rowset,
        );

        $event = $this->_triggerEvent('tinygrid.rowset_current_page', $eventParams);
        if($event)
        {
            $rowset = $event->getParam('rowset');
        }


        foreach($rowset as $index => $row)
        {
            $attributes = array();

            #
            $eventParams = array(
                'attributes' => $attributes,
                'index' => $index,
                'row' => $row,
            );

            $event = $this->_triggerEvent('tinygrid.render_row', $eventParams);
            if($event)
            {
                $attributes = (array)$event->getParam('attributes');
                $row = $event->getParam('row');
            }
            

            #
            $html .= sprintf('<tr%s>', $this->_attrToStr($attributes));

            $cols = $this->getColumns();
            foreach($cols as $field => $config)
            {
                $cellConfig = isset($config['cell']) ? (array)$config['cell'] : array();
                $html .= $this->_renderRow($field, $cellConfig, $row);
            }

            $html .= '</tr>';
        }

        return $html;
    }


    /**
     * @return  string
     */    
    protected function _renderRow($field, $config, $row)
    {
        #
        $eventParams = array(
            'cell' => $config,
            'field' => $field,
            'row' => $row,
        );

        $event = $this->_triggerEvent('tinygrid.render_cell', $eventParams);
        if($event)
        {
            $config = (array)$event->getParam('cell');
        }

        $value = isset($row[$field]) ? $row[$field] : null;
        if(isset($config['strip_tags']) && (true == $config['strip_tags']))
        {
            if(isset($row[$field]))
            {
                $value = strip_tags($value);
            }
        }

        #
        $attributes = isset($config['attributes']) ? (array)$config['attributes'] : array();
        $type = isset($config['type']) ? $config['type'] : null;
        if($event)
        {
            $row = $event->getParam('row');
        }
        

        #
        $html = '';
        $html .= sprintf('<td%s>', $this->_attrToStr($attributes));

        switch($type)
        {
            case 'custom':

                $value = isset($config['data']) ? $config['data'] : null;

            break;

           
            case 'html':

                $value = '<code>' . htmlentities($value) . '</code>';

            break;
            
            case 'code':

                $value = '<pre>' . $value . '</pre>';

            break;
            
            case 'enum':

                $value = (isset($config['source']) && is_array($config['source']) && isset($config['source'][$value])) ? $config['source'][$value] : $value;

            break;
            
            /*
            case 'progressbar':

                $field_maximum_value = isset($config['maximum_value']) ? $config['maximum_value'] : 100;
                $show_value = isset($config['show_value']) ? $config['show_value'] : false;
                $style = isset($config['style']) ? $config['style'] : 'progress-bar-default';
                $progress_value = ($value/$field_maximum_value * 100);
                if($show_value !== false){
                    $html .= '<div class="clearfix">
                                <small class="pull-left">'.(($value > 0) ? $value : "").'</small>
                              </div>';    
                }
                $html .= '<div class="progress  xs" style="height: 8px;" title="'.$value.'">
                            <div class="progress-bar '.$style.'" role="progressbar" aria-valuenow="'.$progress_value.'" aria-valuemin="0" aria-valuemax="'.$field_maximum_value.'" style="width: '.$progress_value.'%;"></div>
                          </div>';
            break;
            */
                
            case 'date':

                $format_to = isset($config['date_format_to']) ? $config['date_format_to'] : 'M j, Y H:i:s';
                $format_from = isset($config['date_format_from']) ? $config['date_format_from'] : 'Y-m-d H:i:s';
                $def = isset($config['empty_date']) ? $config['empty_date'] : '-';

                if($value)
                {
                    $value = $this->_getDateFormated($value, $format_from, $format_to, $def);
                }
                else
                {
                    $value = $def;
                }

            break;
            
            case 'relative_date':

                $value = $this->_getRelativeDate($value);

            break;
            
            case 'money':

                $value = (double)$value;
                if(!$value)
                {
                    $value = '0.00';
                }

                $field_money_sign = isset($config['sign']) ? $config['sign'] : '$';
                $field_decimal_places = isset($config['decimal_places']) ? $config['decimal_places'] : 2;
                $field_dec_separator = isset($config['decimal_separator']) ? $config['decimal_separator'] : '.';
                $field_thousands_separator = isset($config['thousands_separator']) ? $config['thousands_separator'] : ',';                

                $value = $field_money_sign . number_format($value, $field_decimal_places, $field_dec_separator, $field_thousands_separator);

            break;

            case 'number': 

                $value = sprintf('<div style="text-align:right">%s</div>', $value);

            break;

            case 'amount':

                $value = (double)$value;

                $field_dec_separator = isset($config['decimal_separator']) ? $config['decimal_separator'] : '.';
                $field_thousands_separator = isset($config['thousands_separator']) ? $config['thousands_separator'] : ',';

                $value = number_format($value, 0, $field_dec_separator, $field_thousands_separator);
                $value = sprintf('<div style="text-align:right">%s</div>', $value);

            break;
            
            case 'password':
            case 'mask':

                $field_symbol = isset($config['symbol']) ? $config['symbol'] : '*';
                $value = str_repeat($field_symbol, strlen($value));

            break;
        }

        if(isset($config['callback']) && is_callable($config['callback']))
        {
            $value = call_user_func($config['callback'], $value, $row, $field, $config);

            if(isset($config['menu']) && is_callable($config['menu']))
            {
                $value .= call_user_func($config['menu'], $row, $config);
            }

            $html .= $value;
        }
        else
        {
            if(isset($config['menu']) && is_callable($config['menu']))
            {
                $value .= call_user_func($config['menu'], $row, $config);
            }

            $html .= $value;
        }

        $html .= '</td>';

        return $html;
    }



    /**
     * _getRelativeDate
     * 
     * Get the relative date string
     * expect parameter as timestamp integer or a date string 
     *
     * @param   mixed   $ts
     * @return  string
     */   
    protected  function _getRelativeDate($ts) 
    {
        if(empty($ts)) { return ''; }
        
        $ts = (!ctype_digit($ts)) ? strtotime($ts) : $ts;

        $diff = time() - $ts;
        if(0 == $diff)
        {
            return 'now';
        }
        elseif($diff > 0)
        {
            $day_diff = floor($diff / 86400);
            if($day_diff == 0)
            {
                if($diff < 60) 
                {
                    return 'just now';
                }

                if($diff < 120) 
                {
                    return '1 minute ago';
                }


                if($diff < 3600) 
                {
                    return floor($diff / 60) . ' minutes ago';
                }

                if($diff < 7200)
                {
                    return '1 hour ago';
                }


                if($diff < 86400)
                {
                    return floor($diff / 3600) . ' hours ago';
                }
            }

            if($day_diff == 1)
            { 
                return 'Yesterday';
            }

            if($day_diff < 7)
            {
                return $day_diff . ' days ago';
            }

            if($day_diff < 31)
            {
                $week = ceil($day_diff / 7); return $week . ' week'.(($week == 1) ? '' :'s').' ago';
            }

            if($day_diff < 60)
            {
                return 'last month';
            }

            #
            $startDate = date('Y-m-d', $ts);
            $endDate   = date('Y-m-d'); 

            #
            $o_month = substr($startDate, 5, 2);
            $o_day = substr($startDate, 8, 2);
            $o_year = substr($startDate, 0, 4);
            $n_month = substr($endDate, 5, 2);
            $n_day = substr($endDate, 8, 2);
            $n_year = substr($endDate, 0, 4);

            if($o_day > $n_day)
            {
                $r_days = 30 + ($n_day - $o_day);
                $o_month++;
            } 
            else
            {
                $r_days = ($n_day - $o_day);
            }
             
            if($o_month > $n_month)
            {
                $r_month = 12 + ($n_month - $o_month);
                $o_year++;
            }
            else
            {
                $r_month = ($n_month - $o_month);
            }

            $r_year = ($n_year - $o_year);

            #
            $numDays = '';
            if($r_year)
            {
                $numDays .= $r_year;
                if(1 == $r_year)
                {
                    $numDays .= ' year ';
                }
                else
                {
                    $numDays .= ' years ';
                }
            }

            if($r_month)
            {
                $numDays .= $r_month;
                if(1 == $r_month)
                {
                    $numDays .= ' month ';
                }
                else
                {
                    $numDays .= ' months ';
                }
            }
            
            if($r_days)
            {
                $numDays .= $r_days;
                if(1 == $r_days)
                {
                    $numDays .= ' day';
                }
                else
                {
                    $numDays .= ' days';
                }
            }

            #
            return $numDays;
            #return date('F d, Y', $ts);
        }
        else
        {
            $diff = abs($diff);
            $day_diff = floor($diff / 86400);
            if($day_diff == 0)
            {
                if($diff < 120)
                {
                    return 'in a minute';
                }

                if($diff < 3600)
                {
                    return 'in ' . floor($diff / 60) . ' minutes';
                }

                if($diff < 7200)
                {
                    return 'in an hour';
                }

                if($diff < 86400)
                {
                    return 'in ' . floor($diff / 3600) . ' hours';
                }
            }

            if($day_diff == 1)
            {
                return 'Tomorrow';
            }

            if($day_diff < 4)
            {
                return date('l', $ts);
            }

            if($day_diff < 7 + (7 - date('w')))
            {
                return 'next week';
            }

            if(ceil($day_diff / 7) < 4)
            {
                $week = ceil($day_diff / 7); 
                return 'in ' . $week . ' week'.(($week == 1) ? '' :'s');
            }

            if(date('n', $ts) == date('n') + 1)
            {
                return 'next month';
            }

            return date('F d, Y', $ts);
        }
    }


    /**
     * _getDateFormated
     * 
     * Convert date from one format to another 
     *
     * @param   string  $dateStr
     * @param   string  $formatFrom
     * @param   string  $formatTo
     * @param   string  $defaultOnEmpty
     * @return  string
     */   
    protected function _getDateFormated($dateStr, $formatFrom = null, $formatTo = null, $defaultOnEmpty = null)
    {
        if(empty($dateStr)) 
        {
            return $defaultOnEmpty;
        }

        if(empty($formatFrom))
        {
            $formatFrom = 'Y-m-d HH:mm:ss';
        }

        if(empty($formatTo))
        {
            $formatTo = 'Y-m-d HH:mm:ss';
        }

        $date = \DateTime::createFromFormat($formatFrom, $dateStr);
        if(!$date)
        {
            return $defaultOnEmpty;
        }

        return $date->format($formatTo);
    }


    /**
     * Generate html for the grid header
     * @return  string
     */
    protected  function _renderHeader()
    {
        if(!$this->getShowHeader())
        {
            return '';
        }

        $cols = $this->getColumns();
         
        $html = '';
        $html .= '<thead>';
        $html .= sprintf('<tr%s>', $this->_attrToStr($this->getHeaderAttr()));
        $counter = 0;
        foreach($cols as $field => $config)
        {
            $header = isset($config['header']) ? $config['header'] : array();
            
            #
            $eventParams = array(
                'field' => $field,
                'header' => $header,
            );

            $event = $this->_triggerEvent('tinygrid.render_header', $eventParams);
            if($event)
            {
                $header = (array)$event->getParam('header');
            }
            

            $label = isset($header['label']) ? $header['label'] : '';
            $attributes = isset($header['attributes']) ? (array)$header['attributes'] : array();
            $sort = isset($header['sort']) ? (bool)$header['sort'] : false;

            $html .= sprintf('<th%s>', $this->_attrToStr($attributes));
            if($sort)
            {
                $order = 'asc';
                $basePath = $this->getBasePath();
                $params = $this->getQueryParams();
                $gridName = $this->getGridName();
                $currSortField = '';

                $sortKey = "{$gridName}{$this->getSortVarName()}";
                if(isset($params[$sortKey]))
                {
                    $currSortField = $params[$sortKey];
                    unset($params[$sortKey]);
                }

                $orderKey = "{$gridName}{$this->getOrderVarName()}";
                if(isset($params[$orderKey]))
                {
                    if('asc' == $params[$orderKey])
                    {
                        $order = 'desc';
                    }
                    
                    unset($params[$orderKey]);
                }

                $params['sort'] = $field;
                $params['order'] = $order;

                $href = $basePath . '?' . http_build_query($params);

                $icon = '<i class="fa fa-sort"></i>';

                if(('asc' == $order) && ($field == $currSortField))
                {
                    $icon = '<i class="fa fa-sort-desc"></i>';
                }
                elseif(('desc' == $order) && ($field == $currSortField))
                {
                    $icon = '<i class="fa fa-sort-asc"></i>';
                }

                $html .= sprintf('<a href="%s">%s %s</a>', $href, $label, $icon);
            }
            else
            {
                $html .= $label;
            }
            
            $html .= '</th>';

            $counter++;
        }

        $html .= '</tr>';
        $html .= '</thead>';

        return $html;
    }



    /**
     * Generate html for the grid header filter
     * @return  string
     */
    protected  function _renderHeaderFilter()
    {
        if(!$this->getShowHeader())
        {
            return '';
        }

        $columns = $this->getColumns();
        $forSearch = $this->_getSearchConfig();

        if(!count($forSearch))
        {
            return '';
        }

        #
        $headerAttr = $this->getHeaderAttr();
        if(isset($headerAttr['class']))
        {
            $headerAttr['class'] = ' filters';
        }
        else
        {
            $headerAttr['class'] = 'filters';
        }

        $html = '';
        $html .= '<thead>';
        $html .= sprintf('<tr%s>', $this->_attrToStr($headerAttr));
        $counter = 0;

        foreach($columns as $field => $null)
        {
            $html .= '<th>';
            if(isset($forSearch[$field]))
            {
                $html .= $this->_buildFilter($field, $forSearch[$field]);
            }
            $html .= '</th>';

            $counter++;
        }

        $html .= '</tr>';
        $html .= '</thead>';

        return $html;
    }



    protected function _buildFilter($field, $config)
    {
        $qParams = $this->getQueryParams();

        $gridName = $this->getGridName();
        $gridSearchClass = $this->_getGridSearchClass();

        $fieldNamePrefix = 'filter_';
        if(!empty($gridName))
        {
            $fieldNamePrefix = "{$gridName}_";
        }

        $fieldName = $fieldNamePrefix . $field;

        $theSearch = '';
        if(isset($qParams[$fieldName]))
        {
            $escaper = $this->getEscaper();
            if($escaper)
            {
                $theSearch = (string)$escaper->escapeHtml($qParams[$fieldName]);
            }
            else
            {
                $theSearch = $qParams[$fieldName];
            }
        }

        #
        if(isset($config['filter_values']))
        {
            $input = '';
            $input .= sprintf('<select name="%s" id="%s" class="form-control form-control-sm input-sm %s">', $fieldName, $fieldName, $gridSearchClass);

            $input .= '<option value="">--</option>';
            foreach($config['filter_values'] as $key => $value)
            {
                $selected = '';
                if((string)$key === $theSearch)
                {
                    $selected = 'selected="selected"';
                }

                $input .= sprintf('<option value="%s" %s>%s</option>', $key, $selected, $value);
            }
            $input .= '</select>';

            return $input;
        }
        else
        {
            return sprintf('<input type="text" value="%s" name="%s" id="%s" class="form-control %s form-control-sm input-sm">', $theSearch, $fieldName, $fieldName, $gridSearchClass);
        }
    }


    /**
     * @return string
     */
    protected function _getGridSearchClass()
    {
        $gridName = $this->getGridName();
        $gridSearchClass = 'grid-search';
        if(!empty($gridName))
        {
            $gridSearchClass .= "-{$gridName}";
        }

        return $gridSearchClass;
    }


    protected function _createLink($type, $current = false, $active = true)
    {
        $params = $this->getQueryParams();
        $gridName = $this->getGridName();

        $pageKey = "{$gridName}{$this->getPageVarName()}";

        if(isset($params[$pageKey]))
        {
            unset($params[$pageKey]);
        }

        #
        $config = $this->getPaginatorConfig();
        $basePath = $this->getBasePath();

        #
        $paginator = $this->getPaginator();
        $pages = $paginator->getPages();


        $labels = $config['labels'];
        $titles = $config['titles'];

        $currentClass = $config['current_class'];
        $disabledClass = $config['disabled_class'];

        if(isset($pages->$type))
        {
            $params[$pageKey] = $pages->$type;
        }
        else
        {
            $params[$pageKey] = $type;
        }
        
        $href = $basePath . '?' . http_build_query($params);
        
        
        $liAttr = $config['li'];
        if(!isset($liAttr['class']))
        {
            $liAttr['class'] = '';
        }

        if(!$active)
        {
            $liAttr['class'] .= " $disabledClass";
        }

        if($current)
        {
            if(is_numeric($type))
            {
                $cls = str_replace($disabledClass, '', $liAttr['class']);
                $liAttr['class'] = "$cls $currentClass";
            }
            else
            {
                $liAttr['class'] .= " $currentClass";
            }
        }


        #
        $aAttr = $config['a'];
        $aAttr['href'] = $href;

        if(isset($titles[$type]))
        {
            $aAttr['title'] = $titles[$type];
        }

        if(isset($labels[$type]))
        {
            $label = $labels[$type];
        }
        else
        {
            $label = $type;
        }

        $liAttr = $this->_attrToStr($liAttr);
        $aAttr = $this->_attrToStr($aAttr);
        
        return sprintf('<li%s><a%s>%s</a></li>', $liAttr, $aAttr, $label);
    }



    /**
     * @param array $arr
     * @return string
     */
    protected function _attrToStr(array $arr)
    {
        $attr = array();
        foreach($arr as $key => $value)
        {
            $attr[] = sprintf('%s="%s"', $key, $value);
        }

        if($attr)
        {
            return ' ' . implode(' ', $attr);
        }
    }



    protected function _getSearchTerms()
    {
        $gridName = $this->getGridName();
        $qParams = $this->getQueryParams();

        #
        $fieldNamePrefix = 'filter_';
        if(!empty($gridName))
        {
            $fieldNamePrefix = "{$gridName}_";
        }

        #
        $config = $this->_getSearchConfig();

        foreach($config as $key => $item)
        {
            $theSearch = '';
            $fieldName = $fieldNamePrefix . $key;
            if(isset($qParams[$fieldName]))
            {
                $theSearch = $qParams[$fieldName];
            }

            $config[$key]['search'] = $theSearch;
        }

        return $config;
    }


    protected function _getSearchConfig()
    {
        $forSearch = array();

        $columns = $this->getColumns();
        foreach($columns as $name => $item)
        {
            if(isset($item['header']))
            {
                $header = $item['header'];
                if(isset($header['filter']) && (true == $header['filter']))
                {
                    $columSearch = isset($header['filter_column']) ? $header['filter_column'] : array($name);

                    $forSearch[$name] = array(
                        'columns' => $columSearch
                    );

                    if(isset($header['filter_values']) && is_array($header['filter_values']))
                    {
                        $forSearch[$name]['filter_values'] = $header['filter_values'];
                    }

                    if(isset($header['filter_values']) && is_array($header['filter_values']))
                    {
                        $forSearch[$name]['filter_type'] = '=';
                    }
                    elseif(isset($header['filter_type']))
                    {
                        $flag = ('=' == $header['filter_type']);
                        $flag = $flag || ('>' == $header['filter_type']);
                        $flag = $flag || ('<' == $header['filter_type']);
                        $flag = $flag || ('>=' == $header['filter_type']);
                        $flag = $flag || ('<=' == $header['filter_type']);

                        if($flag)
                        {
                            $forSearch[$name]['filter_type'] = $header['filter_type'];
                        }
                    }
                }
            }
        }

        return $forSearch;
    }



    protected function _applyFilter($source)
    {
        $config = $this->_getSearchTerms();

        if($source instanceof Select)
        {
            $source = $this->_applyFilterSelect($source, $config);
        }
        elseif($source instanceof \Iterator)
        {
            $source = $this->_applyFilterIterator($source, $config);
        }
        elseif(is_array($source))
        {
            $source = $this->_applyFilterArray($source, $config);
        }

        return $source;
    }


    protected function _applyFilterIterator($source, $config)
    {
        throw new \Exception('Filter Iterator is not implemented');
        return $source;
    }


    protected function _applyFilterArray($source, $config)
    {
        #throw new \Exception('Filter array is not implemented');
        return $source;
    }


    protected function _applyFilterSelect($source, $config)
    {
        $where = $source->getRawState('where');
        if(!$where)
        {
            $where = new Where();
        }
        
        foreach($config as $item)
        {
            $search = (string)$item['search'];
            $filterType = isset($item['filter_type']) ? $item['filter_type'] : null;

            $applyFilterType = false;
            if($filterType)
            {
                $applyFilterType = true;
            }

            if($search !== '')
            {
                foreach($item['columns'] as $column)
                {
                    $s = substr($search, 0, 1);
                    $s2 = substr($search, 0, 2);

                    if(('=' == $filterType) || ('=' == $s))
                    {
                        if(!$applyFilterType)
                        {
                            $search = substr($search, 1);
                        }

                        $where->equalTo($column, $search);
                    }
                    elseif(('>=' == $filterType) || ('>=' == $s2))
                    {
                        if(!$applyFilterType)
                        {
                            $search = substr($search, 2);
                        }

                        $where->greaterThanOrEqualTo($column, $search);
                    }
                    elseif(('<=' == $filterType) || ('<=' == $s2))
                    {
                        if(!$applyFilterType)
                        {
                            $search = substr($search, 2);
                        }

                        $where->lessThanOrEqualTo($column, $search);
                    }
                    elseif(('>' == $filterType) || ('>' == $s))
                    {
                        if(!$applyFilterType)
                        {
                            $search = substr($search, 1);
                        }

                        $where->greaterThan($column, $search);
                    }
                    elseif(('<' == $filterType) || ('<' == $s))
                    {
                        if(!$applyFilterType)
                        {
                            $search = substr($search, 1);
                        }

                        $where->lessThan($column, $search);
                    }
                    else
                    {
                        $where->like($column, "%$search%");
                    }
                }
            }
        }

        $source->where($where);

        return $source;
    }



    /**
     * @return array
     */
    protected function _getSortColumn()
    {
        $qParams = $this->getQueryParams();
        $gridName = $this->getGridName();
        $sortVarName = $this->getSortVarName();

        $key = "{$gridName}{$sortVarName}";
        $sortColumn = array();

        if(isset($qParams[$key]))
        {
            $column = $qParams[$key];
            $cols = $this->getColumns();
            if(isset($cols[$column]))
            {
                $col = $cols[$column];
                if(isset($col['header']) && isset($col['header']['sort']) && (true == $col['header']['sort']))
                {
                    if(isset($col['header']['sort_column']))
                    {
                        if(is_array($col['header']['sort_column']))
                        {
                            $sortColumn = $col['header']['sort_column'];
                        }
                        else
                        {
                            $sortColumn = array($col['header']['sort_column']);
                        }
                    }
                    else
                    {
                        $sortColumn = array($column);
                    }
                }
            }
        }
        else
        {
            $columns = $this->getColumns();
            foreach($columns as $column => $item)
            {
                if(isset($item['header']))
                {
                    if(isset($item['header']['sort_default']))
                    {
                        $sortColumn = array($column);
                        break;
                    }
                }
            }
        }

        return $sortColumn;
    }


    protected function _applySort($source)
    {
        $sortOrder = $this->_getSortOrder();
        $sortColumn = $this->_getSortColumn();

        if($source instanceof Select)
        {
            $source = $this->_applySortSelect($source, $sortOrder, $sortColumn);
        }
        elseif($source instanceof \Iterator)
        {
            $source = $this->_applySortIterator($source, $sortOrder, $sortColumn);
        }
        elseif(is_array($source))
        {
            $source = $this->_applySortArray($source, $sortOrder, $sortColumn);
        }

        return $source;
    }


    protected function _applySortArray($source, $sortOrder, $sortColumn)
    {
        $array_sort = function($array, $cols)
        {
            $colarr = array();
            foreach($cols as $col => $order)
            {
                $colarr[$col] = array();
                foreach($array as $k => $row)
                {
                    $colarr[$col]['_' . $k] = strtolower($row[$col]);
                }
            }

            $eval = 'array_multisort(';
            foreach($cols as $col => $order)
            {
                $eval .= '$colarr[\''.$col.'\'],'.$order.',';
            }

            $eval = substr($eval,0,-1).');';
            eval($eval);

            $ret = array();
            foreach($colarr as $col => $arr)
            {
                foreach($arr as $k => $v)
                {
                    $k = substr($k,1);
                    if (!isset($ret[$k])) $ret[$k] = $array[$k];
                    $ret[$k][$col] = $array[$k][$col];
                }
            }

            return $ret;
        };

        #
        $sort = array();
        foreach($sortColumn as $column)
        {
            $sort[$column] = ('desc' == $sortOrder) ? SORT_DESC : SORT_ASC;
        }

        if(count($sort))
        {
            $source = $array_sort($source, $sort);
        }
        
        return $source;
    }


    protected function _applySortIterator($source, $sortOrder, $sortColumn)
    {
        throw new \Exception('Sorting Iterator is not implemented');
        return $source;
    }


    protected function _applySortSelect($source, $sortOrder, $sortColumn)
    {
        foreach($sortColumn as $column)
        {
            $source->order("$column $sortOrder");
        }
        
        return $source;
    }


    protected function _getSortOrder()
    {
        $qParams = $this->getQueryParams();
        
        $gridName = $this->getGridName();
        $orderVarName = $this->getOrderVarName();

        $key = "{$gridName}{$orderVarName}";
        $sortOrder = 'asc';

        if(isset($qParams[$key]))
        {
            $sortOrder = strtolower($qParams[$key]);
            if(($sortOrder != 'desc') && ($sortOrder != 'asc'))
            {
                $sortOrder = 'asc';
            }
        }
        else
        {
            $columns = $this->getColumns();
            foreach($columns as $column => $item)
            {
                if(isset($item['header']))
                {
                    if(isset($item['header']['sort_default']))
                    {
                        $sortOrder = $item['header']['sort_default'];
                        break;
                    }
                }
            }
        }

        return $sortOrder;
    }


    /**
     * @return Event
     */
    protected function _triggerEvent($name, array $eventParams)
    {
        $eventManager = $this->getEventManager();
        if($eventManager)
        {
            $event = new Event($name, $this, $eventParams);
            $this->getEventManager()->triggerEvent($event);
            return $event;
        }
    }





    protected function _buildDatasource()
    {
        return $this;
    }


    protected function _buildColumns()
    {
        return $this;
    }
}