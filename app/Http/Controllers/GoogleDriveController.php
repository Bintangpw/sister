<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use App\Models\User;

class GoogleDriveController extends Controller
{
    public $gClient;

    function __construct(){
        
        $this->gClient = new \Google_Client();
        
        $this->gClient->setApplicationName('Project Web'); // ADD YOUR AUTH2 APPLICATION NAME (WHEN YOUR GENERATE SECRATE KEY)
        $this->gClient->setClientId('600439554432-3err0q4upef32n0dphjtfllk86i891gr.apps.googleusercontent.com');
        $this->gClient->setClientSecret('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');
        $this->gClient->setRedirectUri(route('google.login'));
        $this->gClient->setDeveloperKey('AIzaSyDgVvJRpgcaiPlJmYTbiyQG_CWDuYhbUoY');
        $this->gClient->setScopes(array(               
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/drive'
        ));
        
        $this->gClient->setAccessType("offline");
        
        $this->gClient->setApprovalPrompt("force");
    }
    
    public function googleLogin(Request $request)  {
        
        $google_oauthV2 = new \Google_Service_Oauth2($this->gClient);

        if ($request->get('code')){

            $this->gClient->authenticate($request->get('code'));

            $request->session()->put('token', $this->gClient->getAccessToken());
        }

        if ($request->session()->get('token')){

            $this->gClient->setAccessToken($request->session()->get('token'));
        }

        if ($this->gClient->getAccessToken()){

            //FOR LOGGED IN USER, GET DETAILS FROM GOOGLE USING ACCES
            $user = User::find(1);

            $user->access_token = json_encode($request->session()->get('token'));

            $user->save();       

            dd("Successfully authenticated");
        } else{
            
            // FOR GUEST USER, GET GOOGLE LOGIN URL
            $authUrl = $this->gClient->createAuthUrl();

            return redirect()->to($authUrl);
        }
    }

    public function googleDriveFileUpload()
    {
        $service = new \Google_Service_Drive($this->gClient);

        $user= User::find(1);

        $this->gClient->setAccessToken(json_decode($user->access_token,true));

        if ($this->gClient->isAccessTokenExpired()) {
            
            // SAVE REFRESH TOKEN TO SOME VARIABLE
            $refreshTokenSaved = $this->gClient->getRefreshToken();

            // UPDATE ACCESS TOKEN
            $this->gClient->fetchAccessTokenWithRefreshToken($refreshTokenSaved);               
            
            // PASS ACCESS TOKEN TO SOME VARIABLE
            $updatedAccessToken = $this->gClient->getAccessToken();
            
            // APPEND REFRESH TOKEN
            $updatedAccessToken['refresh_token'] = $refreshTokenSaved;
            
            // SET THE NEW ACCES TOKEN
            $this->gClient->setAccessToken($updatedAccessToken);
            
            $user->access_token=$updatedAccessToken;
            
            $user->save();                
        }
        
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'name' => 'Project',             // ADD YOUR GOOGLE DRIVE FOLDER NAME
            'mimeType' => 'application/vnd.google-apps.folder'));

        $folder = $service->files->create($fileMetadata, array('fields' => 'id'));

        printf("Folder ID: %s\n", $folder->id);
        
        $file = new \Google_Service_Drive_DriveFile(array('name' => 'cdrfile.jpg','parents' => array($folder->id)));

        $result = $service->files->create($file, array(

            'data' => file_get_contents(public_path('test.png')), // ADD YOUR FILE PATH WHICH YOU WANT TO UPLOAD ON GOOGLE DRIVE
            'mimeType' => 'application/octet-stream',
            'uploadType' => 'media'
        ));

        $url='https://drive.google.com/open?id='.$result->id;

        dd($result);
    }

    public function showUploadForm()
    {
        return view('google_drive_upload');
    }   
    
    private function getGoogleDriveFolderId($folderName)
    {
        $service = new \Google_Service_Drive($this->gClient);
    
        $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and name='$folderName'";
        $files = $service->files->listFiles($parameters);
    
        if (count($files->getFiles()) > 0) {
            return $files->getFiles()[0]->getId();
        }
    
        return null;
    }
    
    // Add a new method to retrieve a list of files in the "Project" folder
    public function listFiles()
    {
        $service = new \Google_Service_Drive($this->gClient);

        $user = User::find(1);
        $this->gClient->setAccessToken(json_decode($user->access_token, true));

        // Check if the access token is expired
        if ($this->gClient->isAccessTokenExpired()) {
            $refreshTokenSaved = $this->gClient->getRefreshToken();
            $this->gClient->fetchAccessTokenWithRefreshToken($refreshTokenSaved);
            $updatedAccessToken = $this->gClient->getAccessToken();
            $updatedAccessToken['refresh_token'] = $refreshTokenSaved;
            $this->gClient->setAccessToken($updatedAccessToken);

            $user->access_token = json_encode($updatedAccessToken);
            $user->save();
        }

        $folderId = $this->getGoogleDriveFolderId('Project');

        if (!$folderId) {
            // Handle the case when the "Project" folder does not exist
            return view('google_drive_upload')->with('error', 'The "Project" folder does not exist.');
        }

        // Retrieve the list of files in the "Project" folder
        $parameters['q'] = "'$folderId' in parents";
        $files = $service->files->listFiles($parameters);

        return view('list_files')->with('files', $files->getFiles());
    }

    public function uploadFile(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:jpg,png,pdf|max:2048',
        ]);
    
        $service = new \Google_Service_Drive($this->gClient);
    
        $user = User::find(1);
    
        $this->gClient->setAccessToken(json_decode($user->access_token, true));
    
        // Check if the access token is expired
        if ($this->gClient->isAccessTokenExpired()) {
            $refreshTokenSaved = $this->gClient->getRefreshToken();
            $this->gClient->fetchAccessTokenWithRefreshToken($refreshTokenSaved);
            $updatedAccessToken = $this->gClient->getAccessToken();
            $updatedAccessToken['refresh_token'] = $refreshTokenSaved;
            $this->gClient->setAccessToken($updatedAccessToken);
    
            $user->access_token = json_encode($updatedAccessToken);
            $user->save();
        }
    
        // Get the ID of the existing "Project" folder
        $folderId = $this->getGoogleDriveFolderId('Project');
    
        if (!$folderId) {
            // Handle the case when the "Project" folder does not exist
            return view('google_drive_upload')->with('error', 'The "Project" folder does not exist.');
        }
    
        $fileContent = File::get($request->file('file')->getRealPath());
    
        // Create the file in the "Project" folder
        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $request->file('file')->getClientOriginalName(),
            'parents' => [$folderId],
        ]);
    
        $file = $service->files->create($fileMetadata, [
            'data' => $fileContent,
            'mimeType' => $request->file('file')->getClientMimeType(),
            'uploadType' => 'media',
        ]);
    
        // Get the URL of the uploaded file
        $url = 'https://drive.google.com/open?id=' . $file->id;
        return redirect()->route('google.drive.list');
    }
    

    // Add a new method to delete selected files
    public function deleteFiles(Request $request)
    {
        $fileIds = $request->input('file_ids');

        if (empty($fileIds)) {
            return redirect()->route('google.drive.list')->with('error', 'Please select files to delete.');
        }

        $service = new \Google_Service_Drive($this->gClient);

        $user = User::find(1);

        $this->gClient->setAccessToken(json_decode($user->access_token, true));

        if ($this->gClient->isAccessTokenExpired()) {
            $refreshTokenSaved = $this->gClient->getRefreshToken();
            $this->gClient->fetchAccessTokenWithRefreshToken($refreshTokenSaved);
            $updatedAccessToken = $this->gClient->getAccessToken();
            $updatedAccessToken['refresh_token'] = $refreshTokenSaved;
            $this->gClient->setAccessToken($updatedAccessToken);

            $user->access_token = json_encode($updatedAccessToken);
            $user->save();
        }

        foreach ($fileIds as $fileId) {
            // Delete each selected file by its ID
            $service->files->delete($fileId);
        }

        return redirect()->route('google.drive.list')->with('success', 'Selected files have been deleted.');
    }

}