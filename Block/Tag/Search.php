<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Block\Tag;

use Pinterest\PinterestBusinessConnectPlugin\Block\Adminhtml\Setup;

class Search extends Setup
{
    /**
     * Dispatch the search event with the required data
     *
     * @param string $eventId
     * @param string $searchTerm
     */
    public function trackSearchEvent($eventId, $searchTerm)
    {
        $this->_eventManager->dispatch(
            "pinterest_commereceintegrationextension_search_after",
            [
                "event_id" => $eventId,
                "event_name" => "search",
                "custom_data" => [
                    "search_string" => $searchTerm
                ],
            ]
        );
    }

    /**
     * Get the query parameter used for search
     */
    public function getSearchQuery()
    {
        return htmlspecialchars(
            $this->getRequest()->getParam("q"),
            ENT_QUOTES,
            "UTF-8"
        );
    }
}
