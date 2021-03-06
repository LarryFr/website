<?php

class ProjectsModel extends Model
{
    private $returnPage = 'projects';
    
    public function Index()
    {
        $this->query("SELECT p.id, pfr.title title_fr, pen.title title_en, p.first_date_project, p.bVisible, 
                             fe.name framework, p.nbViews,
                             CONCAT(v.num_version, ' (', v.date_version, ')') version
                      FROM project AS p 
                        INNER JOIN project_tr AS pfr ON p.id = pfr.id AND pfr.id_Language = 1
                        INNER JOIN project_tr AS pen ON p.id = pen.id AND pen.id_Language = 2
                        INNER JOIN framework AS fe ON p.id_Framework = fe.id 
                        INNER JOIN proglanguage AS l ON fe.id_ProgLanguage = l.id 
                        LEFT JOIN version AS v 
                            ON v.id = (SELECT max(vv.id) 
                                       FROM version AS vv 
                                       WHERE vv.id_Project = p.id 
                                       ORDER BY vv.date_version DESC)
                      ORDER BY p.bVisible DESC, v.date_version DESC, p.first_date_project DESC");
        $rows = $this->resultSet();
        $this->close();
        return $rows;
    }

    public function Add()
    {
        $post = filter_input_array(INPUT_POST, FILTER_SANITIZE_ENCODED);
        if ($post['submit'])
        {
            if ($post['title_fr'] == '' || $post['title_en'] == '' || $post['description_fr'] == '' || $post['description_en'] == '' || $post['desc_fr'] == '' || $post['desc_en'] == '')
            {
                Messages::setMsg('Please fill in all mandatory fields', 'error');
            }
            else if (($post['num_version'] != '' && $post['date_version'] == '') || ($post['num_version'] == '' && $post['date_version'] != ''))
            {
                Messages::setMsg('If "Version number" is filled, "Version date" must be filled too (or vice versa).', 'error');
            }
            else if ($post['date_version'] != '' && $post['date_version'] < $post['dateproject'])
            {
                Messages::setMsg("Date of version must be greater or equal than the project's date", 'error');
            }
            else
            {
                $img_blob = '';
                $img_taille = 0;
                $img_type = '';
                $img_nom = '';
                $taillemax = intval(ConfigModel::getConfig("MAX_FILE_SIZE"));
                if (isset($_FILES['projectimage']) && $_FILES['projectimage']['error'] != 4)
                {
                    $img_taille = $_FILES['projectimage']['size'];
                    $ret = is_uploaded_file($_FILES['projectimage']['tmp_name']);
                    if (!$ret)
                    {
                        Messages::setMsg('Error during file transfert', 'error');
                    }
                    else if ($img_taille > $taillemax)
                    {
                        Messages::setMsg('File oversized', 'error');
                    }
                    else
                    {
                        $img_type = $_FILES['projectimage']['type'];
                        $img_nom  = $_FILES['projectimage']['name'];
                        $img_blob = file_get_contents($_FILES['projectimage']['tmp_name']);
                    }
                }

                // Insert into MySQL
                date_default_timezone_set('Europe/Paris');
                $dateproject = isset($post['dateproject']) && strtotime($post['dateproject']) ? $post['dateproject'] : date("Y-m-d");
                $this->startTransaction();
                //Insertion des données générales
                $this->query("INSERT INTO project (id_Framework, first_date_project, bVisible, website)
                            VALUES (:idframework, :dateproject, :bVisible, :website)");
                $this->bind(':idframework', $post['framework'], PDO::PARAM_INT);
                $this->bind(':dateproject', $dateproject);
                $this->bind(':website', $post['website']);
                //$this->bind(':image', isset($post['image']) ? $post['image'] : 'null');
                $this->bind(':bVisible', (isset($post['bVisible']) ? $post['bVisible'] : 0), PDO::PARAM_INT);
                $resp = $this->execute();
                $id = $this->lastIndexId();
                //Insertion du titre français
                $this->query('INSERT INTO project_tr (id, id_Language, title, description, short_desc)
                            VALUES(:id, 1, :title, :description, :short_desc)');
                $this->bind(':id', $id, PDO::PARAM_INT);
                $this->bind(':title', $post['title_fr']);
                $this->bind(':description', $post['description_fr']);
                $this->bind(':short_desc', $post['desc_fr']);
                $respfr = $this->execute();
                //Insertion du titre anglais
                $this->query('INSERT INTO project_tr (id, id_Language, title, description, short_desc)
                            VALUES(:id, 2, :title, :description, :short_desc)');
                $this->bind(':id', $id, PDO::PARAM_INT);
                $this->bind(':title', $post['title_en']);
                $this->bind(':description', $post['description_en']);
                $this->bind(':short_desc', $post['desc_en']);
                $respen = $this->execute();
                //Insertion de la version
                $this->query('INSERT INTO version (id_Project, num_version, date_version)
                            VALUES(:id, :num_version, :date_version)');
                $this->bind(':id', $id, PDO::PARAM_INT);
                $this->bind(':num_version', isset($post['num_version']) ? $post['num_version'] : '0.0.1' );
                $this->bind(':date_version', isset($post['date_version']) ? $post['date_version'] : $dateproject );
                $respv = $this->execute();
                //Insertion de l'image
                $respi = true;
                if ($img_blob != '')
                {
                    $this->query("INSERT INTO projectimage (name, img_size, img_type, img_blob, id_Project)
                                VALUES (:name, :size, :type, :blob, :id_Project)");
                    $this->bind(':id_Project', $id, PDO::PARAM_INT);
                    $this->bind(':name', $img_nom);
                    $this->bind(':size', $img_taille);
                    $this->bind(':type', $img_type);
                    $this->bind(':blob', base64_encode($img_blob));
                    $respi = $this->execute();
                }

                //Verify
                if($resp && $respen && $respfr && $respi && $respv)
                {
                    $this->commit();
                    $this->close();
                    $this->returnToPage($this->returnPage);
                }
                $this->rollback();
                $this->close();
                Messages::setMsg('Error(s) during insert : [resp='.$resp.', respen='.$respen.', respfr='.$respfr.', respi='.$respi.']', 'error');
            }
        }
        return;
    }

    public function Update()
    {
        $post = filter_input_array(INPUT_POST, FILTER_SANITIZE_ENCODED);
        if ($post['submit'])
        {
            if ($post['title_fr'] == '' || $post['title_en'] == '' || $post['description_fr'] == '' || $post['description_en'] == '' || $post['desc_fr'] == '' || $post['desc_en'] == '')
            {
                Messages::setMsg('Please fill in all mandatory fields', 'error');
                return;
            }
            $img_blob = '';
            $img_taille = 0;
            $img_type = '';
            $img_nom = '';

            $taillemax = intval(ConfigModel::getConfig("MAX_FILE_SIZE"));

            if (isset($_FILES['projectimage']) && $_FILES['projectimage']['error'] != 4)
            {
                $ret = is_uploaded_file($_FILES['projectimage']['tmp_name']);
                if (!$ret)
                {
                    Messages::setMsg('Error during file transfert', 'error');
                    return;
                }
                $img_taille = $_FILES['projectimage']['size'];
                if ($img_taille > $taillemax)
                {
                    Messages::setMsg('File oversized', 'error');
                    return;
                }
                $img_type = $_FILES['projectimage']['type'];
                $img_nom  = $_FILES['projectimage']['name'];
                $img_blob = file_get_contents($_FILES['projectimage']['tmp_name']);
            }

            date_default_timezone_set('Europe/Paris');
            $this->startTransaction();
            $this->query('UPDATE project 
                          SET id_Framework = :id_Framework, first_date_project = :first_date_project,
                              bVisible = :bVisible, website=:website
                          WHERE id = :id');
            $this->bind(':id_Framework', $post['framework'], PDO::PARAM_INT);
            $this->bind(':first_date_project', isset($post['dateproject']) && strtotime($post['dateproject']) ? $post['dateproject'] : 'null');
            $this->bind(':bVisible', (isset($post['bVisible']) ? $post['bVisible'] : 0), PDO::PARAM_INT);
            $this->bind(':website', $post['website']);
            $this->bind(':id', $post['id'], PDO::PARAM_INT);
            $resp = $this->execute();
            // Mise à jour du titre FR
            $this->query('UPDATE project_tr 
                            SET title = :title, description = :description, short_desc = :short_desc
                            WHERE id = :id AND id_Language = 1');
            $this->bind(':title', $post['title_fr']);
            $this->bind(':description', $post['description_fr']);
            $this->bind(':short_desc', $post['desc_fr']);
            $this->bind(':id', $post['id'], PDO::PARAM_INT);
            $resfr = $this->execute();
            // Mise à jour du titre EN
            $this->query('UPDATE project_tr 
                            SET title = :title, description = :description, short_desc = :short_desc
                            WHERE id = :id AND id_Language = 2');
            $this->bind(':title', $post['title_en']);
            $this->bind(':description', $post['description_en']);
            $this->bind(':short_desc', $post['desc_en']);
            $this->bind(':id', $post['id'], PDO::PARAM_INT);
            $resen = $this->execute();
            //Insertion de la version si date plus récente
            $vm = new VersionModel();
            $version = $vm->getLastVersion($post['id']);
            if ($version['num_version'] != $post['num_version'] || $version['date_version'] != $post['date_version'])
            {
                $this->query('INSERT INTO version (id_Project, num_version, date_version)
                              VALUES(:id_project, :num_version, :date_version)');
                $this->bind(':id_project', $post['id'], PDO::PARAM_INT);
            }
            else
            {
                $this->query("UPDATE version SET num_version = :num_version, date_version = :date_version
                              WHERE id = :id");
                $this->bind(':id', $version['id'], PDO::PARAM_INT);
            }
            $this->bind(':num_version', $post['num_version']);
            $this->bind(':date_version', $post['date_version']);
            $respv = $this->execute();
            //Insertion de l'image
            $respid = true;
            $respi = true;
            if ($img_blob != '')
            {
                $this->query("DELETE FROM projectimage WHERE id_Project = :id_Project");
                $this->bind(':id_Project', $post['id'], PDO::PARAM_INT);
                $respid = $this->execute();
                $this->query("INSERT INTO projectimage (name, img_size, img_type, img_blob, id_Project)
                              VALUES (:name, :size, :type, :blob, :id_Project)");
                $this->bind(':id_Project', $post['id'], PDO::PARAM_INT);
                $this->bind(':name', $img_nom);
                $this->bind(':size', $img_taille);
                $this->bind(':type', $img_type);
                $this->bind(':blob', base64_encode($img_blob));
                $respi = $this->execute();
            }

            //Verify
            if($resp && $resfr && $resen && $respid && $respi && $respv)
            {
                $this->commit();
                $this->close();
                $this->returnToPage($this->returnPage);
            }
            $this->rollBack();
            $this->close();
            Messages::setMsg('Error(s) during update', 'error');
        }
        $get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
        $this->query("SELECT p.id, p.first_date_project, p.id_Framework, p.bVisible,
                             pfr.title title_fr, pen.title title_en, p.website,
                             pfr.description description_fr, pen.description description_en,
                             pfr.short_desc desc_fr, pen.short_desc desc_en,
                             pi.name, pi.img_size, pi.img_type, pi.img_blob,
                             v.num_version, v.date_version
                      FROM project AS p 
                        INNER JOIN project_tr AS pfr ON p.id = pfr.id AND pfr.id_Language = 1
                        INNER JOIN project_tr AS pen ON p.id = pen.id AND pen.id_Language = 2
                        LEFT JOIN projectimage AS pi ON p.id = pi.id_Project
                        LEFT JOIN version AS v ON v.id = 
                            (SELECT max(vv.id) 
                             FROM version AS vv 
                             WHERE vv.id_Project = p.id 
                             ORDER BY vv.date_version DESC)
                      WHERE p.id = :id");
        $this->bind(':id', $get['id'], PDO::PARAM_INT);
        $rows = $this->single();
        $this->close();
        if (!$rows)
        {
            Messages::setMsg('Record "'.$get['id'].'" not found', 'error');
            $this->returnToPage($this->returnPage);
        }
        return $rows;
    }

    public function Delete()
    {
        $post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
        if (isset($post['todelete']))
        {
            //Mise à jour de la base
            $this->startTransaction();
            $this->query('DELETE FROM project WHERE id = :id');
            $this->bind(':id', $post['id'], PDO::PARAM_INT);
            $resp = $this->execute();
            $this->query('DELETE FROM project_tr WHERE id = :id');
            $this->bind(':id', $post['id'], PDO::PARAM_INT);
            $resptr = $this->execute();

            if($resp && $resptr)
            {
                $this->commit();
            }
            else
            {
                $this->rollBack();
            }
            $this->close();
            $this->returnToPage($this->returnPage);
        }
        $get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
        $this->query("SELECT id, pfr.title title_fr, pen.title title_en
                      FROM project
                        INNER JOIN project_tr AS pfr ON p.id = pfr.id AND pfr.id_Language = 1
                        INNER JOIN project_tr AS pen ON p.id = pen.id AND pen.id_Language = 2
                      WHERE id = :id");
        $this->bind(':id', $get['id'], PDO::PARAM_INT);
        $rows = $this->single();
        $this->close();
        if (!$rows)
        {
            Messages::setMsg('Record "'.$get['id'].'" not found', 'error');
            $this->returnToPage($this->returnPage);
        }
        return $rows;
    }

    public function getImage($id)
    {
        $this->query("SELECT name, img_size, img_type, img_blob
                      FROM projectimage
                      WHERE id_Project = :id");
        $this->bind(':id', $id, PDO::PARAM_INT);
        $rows = $this->single();
        $this->close();
        return $rows;
    }

    public function getList()
    {
        $this->query("SELECT p.id, pfr.title title_fr, pen.title title_en
                      FROM project AS p
                        INNER JOIN project_tr AS pfr ON p.id = pfr.id AND pfr.id_Language = 1
                        INNER JOIN project_tr AS pen ON p.id = pen.id AND pen.id_Language = 2");
        $rows = $this->resultSet();
        $this->close();
        return $rows;
    }

    public function getNbProjects($isActive = false)
    {
        $query = "SELECT COUNT(id) nb FROM project ";
        if($isActive)
        {
            $query .= " WHERE bVisible = 1";
        }
        $this->query($query);
        $rows = $this->single();
        $this->close();
        return $rows['nb'];
    }

    public function getNbActiveProjects() { return $this->getNbProjects(true); }

    public function getDateProject($id)
    {
        $this->query("SELECT first_date_project FROM project WHERE id = :id");
        $this->bind(':id', $id, PDO::PARAM_INT);
        $rows = $this->single();
        $this->close();
        return $rows['first_date_project'];
    }
}
?>