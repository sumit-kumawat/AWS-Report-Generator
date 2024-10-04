<?php
require 'vendor/autoload.php'; // Include AWS SDK and DomPDF dependencies

use Aws\Ec2\Ec2Client;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Dompdf\Dompdf;
use Dompdf\Options;

// AWS Regions to Scan
$regions = ['us-east-1', 'us-west-2', 'eu-west-1', 'ap-south-1']; // Add or remove regions as needed

// Handling form submission for AWS credentials
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $access_key = $_POST['access_key'] ?? '';
    $secret_key = $_POST['secret_key'] ?? '';
    $session_token = $_POST['session_token'] ?? ''; // Optional session token

    // Ensure access and secret keys are provided
    if (empty($access_key) || empty($secret_key)) {
        echo "Please provide both AWS Access Key and Secret Key.";
        exit;
    }

    // Create AWS Credentials object
    $credentials = new Credentials($access_key, $secret_key, $session_token);

    // Array to hold scanned services data
    $allServicesData = [];

    // Loop through each region to scan services
    foreach ($regions as $region) {
        try {
            // Create EC2 client for each region
            $ec2Client = new Ec2Client([
                'region'      => $region,
                'version'     => 'latest',
                'credentials' => $credentials,
            ]);

            // Scan different AWS resources, and collect their data
            // 1. VPCs
            $result = $ec2Client->describeVpcs();
            $vpcCount = count($result['Vpcs']);
            if ($vpcCount > 0) {
                $allServicesData[] = ['Region' => $region, 'Service' => 'VPCs', 'Count' => $vpcCount];
            }

            // 2. Subnets
            $result = $ec2Client->describeSubnets();
            $subnetCount = count($result['Subnets']);
            if ($subnetCount > 0) {
                $allServicesData[] = ['Region' => $region, 'Service' => 'Subnets', 'Count' => $subnetCount];
            }

            // Continue with additional services...
            // (Internet Gateways, Route Tables, Security Groups, NAT Gateways, etc.)
            // Add them similarly as VPCs and Subnets using `describeX()` methods for each service
            // ...
            // For example:
            $result = $ec2Client->describeInternetGateways();
            $internetGatewayCount = count($result['InternetGateways']);
            if ($internetGatewayCount > 0) {
                $allServicesData[] = ['Region' => $region, 'Service' => 'Internet Gateways', 'Count' => $internetGatewayCount];
            }

            // Example: NAT Gateways
            $result = $ec2Client->describeNatGateways();
            $natGatewayCount = count($result['NatGateways']);
            if ($natGatewayCount > 0) {
                $allServicesData[] = ['Region' => $region, 'Service' => 'NAT Gateways', 'Count' => $natGatewayCount];
            }

            // Add more services if needed...

        } catch (AwsException $e) {
            // Catch AWS errors and continue scanning other regions
            echo "Error in region {$region}: " . $e->getAwsErrorMessage();
            continue; // Skip to the next region
        }
    }

    // Generate a PDF report if there is any data to report
    if (!empty($allServicesData)) {
        generatePdfReport($allServicesData);
        echo "AWS Services report generated and downloaded.";
    } else {
        echo "No resources found across the specified regions.";
    }
}

// Function to generate PDF report using DomPDF
function generatePdfReport($servicesData) {
    $options = new Options();
    $options->set('isRemoteEnabled', true); // Enable remote assets (if required)
    $dompdf = new Dompdf($options);

    // Create HTML content for the PDF report
    $html = '<style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>';

    $html .= '<h1>AWS Services Utilization Report</h1>';
    $html .= '<table>';
    $html .= '<thead>
                <tr>
                    <th>Region</th>
                    <th>Service</th>
                    <th>Resource Count</th>
                </tr>
              </thead>';
    $html .= '<tbody>';

    // Populate the table with service data
    foreach ($servicesData as $service) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($service['Region']) . '</td>';
        $html .= '<td>' . htmlspecialchars($service['Service']) . '</td>';
        $html .= '<td>' . htmlspecialchars($service['Count']) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    // Load HTML content into DomPDF and render PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape'); // Set paper size and orientation
    $dompdf->render();

    // Output the generated PDF directly to the browser
    $dompdf->stream("aws_services_report.pdf", ["Attachment" => true]);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWS Services Scanner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
        }
        form {
            width: 300px;
            margin-bottom: 20px;
        }
        label {
            font-weight: bold;
            margin-top: 10px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        input[type="submit"] {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
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

</body>
</html>
