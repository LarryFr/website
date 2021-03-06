<?php

class NavBarModel extends Model
{
    public function getVisibleItems()
    {
        $this->query("SELECT i.destination, i.bPage, itr.title
                      FROM indexitems AS i
                        INNER JOIN indexitems_tr AS itr ON i.id = itr.id
                        INNER JOIN language AS l ON itr.id_Language = l.id AND l.code = :language
                      WHERE i.id_Category = 1 AND i.bVisible = 1 AND i.bInNavBar = 1
                      ORDER BY i.sortOrder");
        $this->bind(':language', $_SESSION['language']);
        $rows = $this->resultSet();
        $this->close();
        return $rows;
    }
}

?>