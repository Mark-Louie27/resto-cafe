 <?php
    function handle_update_customer()
    {
        $conn = db_connect();

        try {
            $stmt = $conn->prepare("UPDATE users SET 
                      first_name = ?, 
                      last_name = ?, 
                      email = ?, 
                      username = ?, 
                      phone = ? 
                      WHERE user_id = ?");
            $stmt->bind_param(
                "sssssi",
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['username'],
                $_POST['phone'],
                $_POST['user_id']
            );
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE customers SET 
                      birth_date = ?, 
                      preferences = ? 
                      WHERE customer_id = ?");
            $stmt->bind_param(
                "ssi",
                $_POST['birth_date'],
                $_POST['preferences'],
                $_POST['customer_id']
            );
            $stmt->execute();

            set_flash_message('Customer updated successfully', 'success');
        } catch (Exception $e) {
            set_flash_message('Error updating customer: ' . $e->getMessage(), 'error');
        }

        header('Location: customers.php');
        exit();
    }

    function handle_update_loyalty()
    {
        $conn = db_connect();

        try {
            $stmt = $conn->prepare("UPDATE customers SET 
                      loyalty_points = ?, 
                      membership_level = ? 
                      WHERE customer_id = ?");
            $stmt->bind_param(
                "isi",
                $_POST['loyalty_points'],
                $_POST['membership_level'],
                $_POST['customer_id']
            );
            $stmt->execute();

            set_flash_message('Loyalty program updated successfully', 'success');
        } catch (Exception $e) {
            set_flash_message('Error updating loyalty: ' . $e->getMessage(), 'error');
        }

        header('Location: customers.php');
        exit();
    }

    function handle_toggle_status()
    {
        $conn = db_connect();

        try {
            // Get user_id from customer_id
            $stmt = $conn->prepare("SELECT user_id FROM customers WHERE customer_id = ?");
            $stmt->bind_param("i", $_POST['customer_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Customer not found");
            }

            $customer = $result->fetch_assoc();
            $user_id = $customer['user_id'];

            // Toggle status
            $new_status = $_POST['new_status'];
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            $stmt->execute();

            set_flash_message("Customer account " . ($new_status === 'inactive' ? 'deactivated' : 'activated') . " successfully", 'success');
        } catch (Exception $e) {
            set_flash_message('Error toggling customer status: ' . $e->getMessage(), 'error');
        }

        header('Location: customers.php');
        exit();
    }

    // Define get_customer_data function
    function get_customer_datas($user_id)
    {
        global $conn;
        $stmt = $conn->prepare("SELECT c.* FROM customers c WHERE c.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) {
            // Default values if customer data not found
            return [
                'membership_level' => 'Standard',
                'loyalty_points' => 0
            ];
        }
        return $result;
    }

    ?>

