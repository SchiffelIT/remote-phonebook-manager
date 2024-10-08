<?php
/*
 * Copyright 2014 IMAGIN Sp. z o.o. - imagin.pl
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Form\NewFileForm;
use App\Form\UploadFileForm;
use App\Service\XMLFileService;
use App\Service\GigasetXMLFileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SourceController extends AbstractController
{
    /**
     * It should show or redirect to default routing when file found
     */
    public function indexAction()
    {
        return $this->render(
            'Source/index.html.twig'
        );
    }

    /**
     * @param Request $request
     * @return string|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function uploadAction(Request $request)
    {
        $createUploadFileForm = new UploadFileForm(Forms::createFormFactory());
        $uploadFileForm = $createUploadFileForm->createForm();

        if ($request->getMethod() === 'POST') {
            $uploadFileForm->handleRequest();

            if ($uploadFileForm->isValid()) {
                $file = $request->files->get($uploadFileForm->getName());
                $path = "pb";
                $filename = $file['fileToUpload']->getClientOriginalName();
                $extension = substr($filename, -4);

                if($extension == ".xml") {
                    $file['fileToUpload']->move($path, $filename);
                    $new_filename = $filename;

                    
                } elseif($extension == ".csv") {
                    $new_filename = substr($filename, 0, -4) . '.xml';

                    if(substr($filename, -8)== ".xml.csv"){
                        $new_filename = substr($filename, 0, -4);
                    }

                    $file['fileToUpload']->move($path, $filename);

                    $file_content = file($path . '/' . $filename);

                    $csv = array_map(
                        'str_getcsv',
                        $file_content,
                        array_fill(0, count($file_content),";")
                    );

                    $data = array();
                    $data['phoneRecords'] = array();

                    foreach($csv as $line) {
                        $record = array();
                        $record['recordName'] = $line[0];
                        $record['phoneNumbers'] = array();

                        for($i=1; $i < count($line); $i++) {
                            $record['phoneNumbers'][] = array("phoneNumber" => $line[$i]);
                        }

                        $data['phoneRecords'][] = $record;
                    }

                    $XMLPattern = new XMLFileService($new_filename, "pb");
                    $XMLToSave = $XMLPattern->generateProperXML($data);
                    $XMLPattern->saveFile($XMLToSave);

                    unlink($path . '/' . $filename);


                } else {
                    return $this->redirect(
                        $this->generateUrl('source')
                    );
                }
                $XMLPattern = new XMLFileService($new_filename, "pb");
                $XMLToSave = new GigasetXMLFileService($new_filename, "pb");
                $data= array("phoneRecords"=>array());
                foreach($XMLPattern->loadFile()->children() as $entry){
                    $tmpdata=array("recordName"=>$entry->Name->__toString());
                    if(is_object($entry->Telephone)){
                        $tmpdata['phoneNumbers']=array();
                        foreach($entry->Telephone as $number){
                            $tmpdata["phoneNumbers"][]=array("phoneNumber"=>$number->__toString());
                        }
                    }
                    else{
                        $tmpdata['phoneNumbers']=array();
                        $tmpdata["phoneNumbers"][]=array("phoneNumber"=>$entry->Telephone->__toString());
                    }
                    $data["phoneRecords"][]=$tmpdata;
                }
                $XMLString = $XMLToSave->generateProperXML($data);
                $XMLToSave->saveFile($XMLString);

                return $this->redirect(
                    $this->generateUrl('contact', array('name' => $new_filename))
                );
            }
        }

        return $this->render(
            'Source/upload.html.twig',
            array('uploadFileForm' => $uploadFileForm->createView(),)
        );
    }

    /**
     * @param Request $request
     * @return string|\Symfony\Component\HttpFoundation\RedirectResponse
     * @throws
     */
    public function newAction(Request $request)
    {
        $createNewFileForm = new NewFileForm(Forms::createFormFactory());
        $newFileForm = $createNewFileForm->createForm();

        if ($request->getMethod() === 'POST') {
            $newFileForm->handleRequest();

            if ($newFileForm->isValid()) {
                $filename = $newFileForm->getData();
                $filename = $filename['fileName'];
                $XMLPattern = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"utf-8\" ?><YealinkIPPhoneDirectory></YealinkIPPhoneDirectory>");

                if ($XMLPattern->saveXML('pb/' . $filename . '.xml') !== false) {
                    return $this->redirect(
                        $this->generateUrl('contact', array('name' => $filename . ".xml"))
                    );
                } else {
                    throw \Exception("Problem with save file into given directory");
                }
            }
        }

        return $this->render(
            'Source/new.html.twig',
            array(
                'newFileForm' => $newFileForm->createView(),
            )
        );
    }

    /**
     * @param Request $request
     * @param String $name
     * @return string|\Symfony\Component\HttpFoundation\RedirectResponse
     * @throws
     */
    public function deleteAction(Request $request, $name) {
        //Yealink
        unlink('pb/' . $name);
        //Gigaset
        if(file_exists('pb/gigaset/' . $name)){
            unlink('pb/gigaset/' . $name);
        }

        return $this->redirect(
            $this->generateUrl('source')
        );
    }

    /**
     * @param Request $request
     * @param String $name
     * @return string|\Symfony\Component\HttpFoundation\RedirectResponse
     * @throws
     */
    public function exportCSVAction(Request $request, $name) {
        $XMLService = new XMLFileService($name, 'pb');
        $phonebook = $XMLService->loadFile();

        $response = "";

        foreach($phonebook as $entry) {
            $response .= '"' . $entry->Name . '";';

            foreach($entry->Telephone as $number) {
                $response .= '"' . $number . '";';
            }

            $response = substr($response, 0, -1);
            $response .= "\n";
        }

        return new Response(
            $response,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $name . '.csv"'
            ]
        );
    }

    /**
     * @return string
     */
    public function listAction()
    {
        $sourceDirectory = "pb";
        $fileList = $this->searchXMLFiles($sourceDirectory);

        return $this->render(
            'Source/list.html.twig',
            array(
                'fileList' => $fileList,
            )
        );
    }

    /**
     * Searching for XML files under given directory
     *
     * @param $sourceDirectory
     * @return array|null
     */
    protected function searchXMLFiles($sourceDirectory)
    {
        $i = 0;
        $fileNamesArray = array();

        if (is_dir($sourceDirectory)) {
            foreach (glob($sourceDirectory . "/*.xml") as $file) {

                $fileParametersArray = explode("/", $file);
                $fileName = end($fileParametersArray);

                $fileNamesArray[$i]['name'] = $fileName;
                $fileNamesArray[$i]['modified'] = date("m.d.Y H:i:s", filemtime($sourceDirectory . "/" . $fileName));
                $i+=1;
            }
        } else {
            return null;
        }

        if (empty($fileNamesArray)) {
            return null;
        }
        $fileNamesArray = $this->files_sort($fileNamesArray, 'modified', SORT_DESC);

        return $fileNamesArray;
    }

    function files_sort($array, $on, $order=SORT_ASC)
    {
        $new_array = array();
        $sortable_array = array();

        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }

            switch ($order) {
                case SORT_ASC:
                    asort($sortable_array);
                    break;
                case SORT_DESC:
                    arsort($sortable_array);
                    break;
            }

            foreach ($sortable_array as $k => $v) {
                $new_array[$k] = $array[$k];
            }
        }

        return $new_array;
    }
}
