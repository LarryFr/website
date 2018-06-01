<?php

class ProjectModel extends Model
{
    const curDB = 'lacombed_projects';

    public function Display()
    {
        $get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
        $this->changeDatabase(self::curDB);
        $this->query("SELECT p.title, p.first_date_project, prev.id previous_id, next.id next_id,
                             ptrfr.description description_fr, ptren.description description_en,
                             CONCAT( fe.name, ' (', l.name, ')' ) framework, pri.img_blob
                      FROM project AS p
                        INNER JOIN frameworkengine AS fe ON p.id_FrameworkEngine = fe.id
                        INNER JOIN proglanguage AS l ON fe.id_ProgLanguage = l.id
                        INNER JOIN project_tr AS ptrfr ON p.id = ptrfr.id AND ptrfr.id_Language = 1
                        INNER JOIN project_tr AS ptren ON p.id = ptren.id AND ptren.id_Language = 2
                        LEFT JOIN projectimage AS pri ON pri.id_Project = p.id
                        LEFT JOIN project AS prev ON prev.id = 
                            (SELECT pp.id FROM project AS pp 
                             WHERE pp.first_date_project <= p.first_date_project AND pp.id <> p.id
                             ORDER BY pp.first_date_project DESC LIMIT 1)
                        LEFT JOIN project AS next ON next.id = 
                            (SELECT pn.id FROM project AS pn 
                             WHERE pn.first_date_project >= p.first_date_project AND pn.id <> p.id
                             ORDER BY pn.first_date_project LIMIT 1)
                      WHERE p.id = :id");
        $this->bind(':id', $get['id']);
        $rows = $this->single();
        $this->close();
        return $rows;
    }
}

?>