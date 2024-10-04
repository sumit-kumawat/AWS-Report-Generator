<?php
require 'vendor/autoload.php'; // Include AWS SDK and DomPDF dependencies

use Aws\Ec2\Ec2Client;
use Aws\Rds\RdsClient;
use Aws\S3\S3Client;
use Aws\Iam\IamClient;
use Aws\Lambda\LambdaClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\CloudWatch\CloudWatchClient;
use Aws\Ecs\EcsClient;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;
use Aws\ElasticsearchService\ElasticsearchServiceClient;
use Aws\CloudFormation\CloudFormationClient;
use Aws\CloudFront\CloudFrontClient;
use Aws\AutoScaling\AutoScalingClient;
use Aws\Waf\WafClient;
use Aws\WafRegional\WafRegionalClient;
use Aws\SecretsManager\SecretsManagerClient;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Aws\ElasticBeanstalk\ElasticBeanstalkClient;
use Aws\CodeDeploy\CodeDeployClient;
use Aws\Batch\BatchClient;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;
use Dompdf\Dompdf;
use Dompdf\Options;

$regions = ['us-east-1', 'us-west-2', 'eu-west-1', 'ap-south-1'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $access_key = $_POST['access_key'] ?? '';
    $secret_key = $_POST['secret_key'] ?? '';
    $session_token = $_POST['session_token'] ?? '';

    if (empty($access_key) || empty($secret_key)) {
        echo "Please provide both AWS Access Key and Secret Key.";
        exit;
    }

    $credentials = new Credentials($access_key, $secret_key, $session_token);
    $allServicesData = [];
    $totalSteps = count($regions) * 21; // Approximate service count
    $completedSteps = 0;

    foreach ($regions as $region) {
        try {
            // EC2 Client
            $ec2Client = new Ec2Client([
                'region'      => $region,
                'version'     => 'latest',
                'credentials' => $credentials,
            ]);

            // VPCs
            $result = $ec2Client->describeVpcs();
            $vpcCount = count($result['Vpcs']);
            if ($vpcCount > 0) $allServicesData[] = ['Region' => $region, 'Service' => 'VPCs', 'Count' => $vpcCount];
            $completedSteps++;

            // Subnets
            $result = $ec2Client->describeSubnets();
            $subnetCount = count($result['Subnets']);
            if ($subnetCount > 0) $allServicesData[] = ['Region' => $region, 'Service' => 'Subnets', 'Count' => $subnetCount];
            $completedSteps++;

            // RDS Client
            $rdsClient = new RdsClient([
                'region'      => $region,
                'version'     => 'latest',
                'credentials' => $credentials,
            ]);

            // DB Instances
            $result = $rdsClient->describeDBInstances();
            $dbInstanceCount = count($result['DBInstances']);
            if ($dbInstanceCount > 0) $allServicesData[] = ['Region' => $region, 'Service' => 'DB Instances', 'Count' => $dbInstanceCount];
            $completedSteps++;

            // Other AWS Services...
            // Add the remaining services similarly...

            // SNS
            $snsClient = new SnsClient([
                'region'      => $region,
                'version'     => 'latest',
                'credentials' => $credentials,
            ]);
            $result = $snsClient->listSubscriptions();
            $subscriptionCount = count($result['Subscriptions']);
            if ($subscriptionCount > 0) $allServicesData[] = ['Region' => $region, 'Service' => 'Subscriptions', 'Count' => $subscriptionCount];
            $completedSteps++;

            // Update progress bar
            echo "<script>updateProgressBar(" . intval(($completedSteps / $totalSteps) * 100) . ");</script>";

        } catch (AwsException $e) {
            echo "Error in region {$region}: " . $e->getAwsErrorMessage();
            continue;
        }
    }

    // Generate PDF and provide download link
    if (!empty($allServicesData)) {
        generatePdfReport($allServicesData);
        echo "<script>document.getElementById('progress').style.display = 'none'; document.getElementById('pdf-link').style.display = 'block';</script>";
    } else {
        echo "No resources found.";
    }
}

function generatePdfReport($servicesData) {
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    $html = '<style>table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid black; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>';
    $html .= '<h1>AWS Services Utilization Report</h1>';
    $html .= '<table><thead><tr><th>Region</th><th>Service</th><th>Resource Count</th></tr></thead><tbody>';

    foreach ($servicesData as $service) {
        $html .= '<tr><td>' . htmlspecialchars($service['Region']) . '</td><td>' . htmlspecialchars($service['Service']) . '</td><td>' . htmlspecialchars($service['Count']) . '</td></tr>';
    }

    $html .= '</tbody></table>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $pdfPath = 'aws_services_report.pdf';
    file_put_contents($pdfPath, $dompdf->output());
    // Serve the file to the user
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfPath . '"');
    header('Content-Length: ' . filesize($pdfPath));
    readfile($pdfPath);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWS Services Scanner</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; padding: 0; }
        form { width: 300px; margin-bottom: 20px; }
        label { font-weight: bold; margin-top: 10px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 10px; }
        input[type="submit"] { padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        input[type="submit"]:hover { background-color: #0056b3; }
        #progress { width: 100%; background-color: #f3f3f3; margin-top: 20px; }
        #progress-bar { height: 20px; background-color: #4caf50; width: 0%; text-align: center; color: white; }
        #pdf-link { display: none; margin-top: 20px; }
    </style>
</head>
<body>

<h1>AWS Services Scanner</h1>

<form method="post" action="">
    <label for="access_key">AWS Access Key:</label>
    <input type="text" id="access_key" name="access_key" required>

    <label for="secret_key">AWS Secret Key:</label>
    <input type="password" id="secret_key" name="secret_key" required>

    <label for="session_token">AWS Session Token (Optional):</label>
    <input type="text" id="session_token" name="session_token">

    <input type="submit" value="Scan AWS Services">
</form>

<div id="progress">
    <div id="progress-bar">0%</div>
</div>

<div id="pdf-link">
    <a href="aws_services_report.pdf" target="_blank">Download your PDF report</a>
</div>

<script>
    // JavaScript to update the progress bar
    function updateProgressBar(percentage) {
        var progressBar = document.getElementById('progress-bar');
        progressBar.style.width = percentage + '%';
        progressBar.textContent = percentage + '%';
    }
</script>

</body>
</html>
