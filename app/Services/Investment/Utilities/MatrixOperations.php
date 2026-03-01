<?php

declare(strict_types=1);

namespace App\Services\Investment\Utilities;

/**
 * Matrix operations for portfolio optimization
 * Provides linear algebra functions needed for MPT calculations
 */
class MatrixOperations
{
    /**
     * Multiply two matrices
     *
     * @param  array  $a  Matrix A (m x n)
     * @param  array  $b  Matrix B (n x p)
     * @return array Result matrix (m x p)
     */
    public function multiply(array $a, array $b): array
    {
        $rowsA = count($a);
        $colsA = count($a[0]);
        $colsB = count($b[0]);

        $result = [];
        for ($i = 0; $i < $rowsA; $i++) {
            $result[$i] = [];
            for ($j = 0; $j < $colsB; $j++) {
                $sum = 0;
                for ($k = 0; $k < $colsA; $k++) {
                    $sum += $a[$i][$k] * $b[$k][$j];
                }
                $result[$i][$j] = $sum;
            }
        }

        return $result;
    }

    /**
     * Transpose a matrix
     *
     * @param  array  $matrix  Input matrix
     * @return array Transposed matrix
     */
    public function transpose(array $matrix): array
    {
        $rows = count($matrix);
        $cols = count($matrix[0]);

        $result = [];
        for ($i = 0; $i < $cols; $i++) {
            for ($j = 0; $j < $rows; $j++) {
                $result[$i][$j] = $matrix[$j][$i];
            }
        }

        return $result;
    }

    /**
     * Calculate dot product of two vectors
     *
     * @param  array  $a  Vector A
     * @param  array  $b  Vector B
     * @return float Dot product
     */
    public function dotProduct(array $a, array $b): float
    {
        $sum = 0;
        $n = count($a);

        for ($i = 0; $i < $n; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }

    /**
     * Calculate matrix determinant (2x2 or 3x3)
     *
     * @param  array  $matrix  Square matrix
     * @return float Determinant
     */
    public function determinant(array $matrix): float
    {
        $n = count($matrix);

        if ($n === 1) {
            return $matrix[0][0];
        }

        if ($n === 2) {
            return $matrix[0][0] * $matrix[1][1] - $matrix[0][1] * $matrix[1][0];
        }

        // For larger matrices, use recursive cofactor expansion (simplified for 3x3)
        if ($n === 3) {
            return $matrix[0][0] * ($matrix[1][1] * $matrix[2][2] - $matrix[1][2] * $matrix[2][1])
                - $matrix[0][1] * ($matrix[1][0] * $matrix[2][2] - $matrix[1][2] * $matrix[2][0])
                + $matrix[0][2] * ($matrix[1][0] * $matrix[2][1] - $matrix[1][1] * $matrix[2][0]);
        }

        throw new \InvalidArgumentException('Determinant calculation only supported for matrices up to 3x3');
    }

    /**
     * Calculate inverse of a 2x2 matrix
     *
     * @param  array  $matrix  2x2 matrix
     * @return array Inverse matrix
     */
    public function inverse2x2(array $matrix): array
    {
        $det = $this->determinant($matrix);

        if (abs($det) < 1e-10) {
            throw new \InvalidArgumentException('Matrix is singular and cannot be inverted');
        }

        return [
            [$matrix[1][1] / $det, -$matrix[0][1] / $det],
            [-$matrix[1][0] / $det, $matrix[0][0] / $det],
        ];
    }

    /**
     * Scalar multiplication of a matrix
     *
     * @param  array  $matrix  Input matrix
     * @param  float  $scalar  Scalar value
     * @return array Resulting matrix
     */
    public function scalarMultiply(array $matrix, float $scalar): array
    {
        $result = [];
        foreach ($matrix as $i => $row) {
            foreach ($row as $j => $value) {
                $result[$i][$j] = $value * $scalar;
            }
        }

        return $result;
    }

    /**
     * Add two matrices
     *
     * @param  array  $a  Matrix A
     * @param  array  $b  Matrix B
     * @return array Sum matrix
     */
    public function add(array $a, array $b): array
    {
        $rows = count($a);
        $cols = count($a[0]);

        $result = [];
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $result[$i][$j] = $a[$i][$j] + $b[$i][$j];
            }
        }

        return $result;
    }

    /**
     * Create identity matrix
     *
     * @param  int  $n  Size of matrix
     * @return array Identity matrix
     */
    public function identity(int $n): array
    {
        $result = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $result[$i][$j] = ($i === $j) ? 1.0 : 0.0;
            }
        }

        return $result;
    }

    /**
     * Cholesky decomposition: decompose symmetric positive-definite matrix A into L * L^T.
     *
     * @param  array  $matrix  Symmetric positive-definite matrix
     * @return array Lower triangular matrix L
     *
     * @throws \InvalidArgumentException If matrix is not positive-definite
     */
    public function choleskyDecomposition(array $matrix): array
    {
        $n = count($matrix);
        $L = array_fill(0, $n, array_fill(0, $n, 0.0));

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j <= $i; $j++) {
                $sum = 0.0;
                for ($k = 0; $k < $j; $k++) {
                    $sum += $L[$i][$k] * $L[$j][$k];
                }

                if ($i === $j) {
                    $diag = $matrix[$i][$i] - $sum;
                    if ($diag <= 0) {
                        throw new \InvalidArgumentException('Matrix is not positive-definite');
                    }
                    $L[$i][$j] = sqrt($diag);
                } else {
                    $L[$i][$j] = ($matrix[$i][$j] - $sum) / $L[$j][$j];
                }
            }
        }

        return $L;
    }

    /**
     * Multiply a matrix by a vector: result = M * v
     *
     * @param  array  $matrix  Matrix (n x m)
     * @param  array  $vector  Vector (m elements)
     * @return array Result vector (n elements)
     */
    public function multiplyVector(array $matrix, array $vector): array
    {
        $result = [];
        foreach ($matrix as $row) {
            $sum = 0.0;
            foreach ($row as $j => $value) {
                $sum += $value * $vector[$j];
            }
            $result[] = $sum;
        }

        return $result;
    }

    /**
     * Calculate quadratic form: x^T * A * x
     * Used for portfolio variance calculation
     *
     * @param  array  $x  Vector (portfolio weights)
     * @param  array  $a  Matrix (covariance matrix)
     * @return float Quadratic form result (portfolio variance)
     */
    public function quadraticForm(array $x, array $a): float
    {
        // Calculate A * x
        $ax = [];
        $n = count($x);
        for ($i = 0; $i < $n; $i++) {
            $sum = 0;
            for ($j = 0; $j < $n; $j++) {
                $sum += $a[$i][$j] * $x[$j];
            }
            $ax[$i] = $sum;
        }

        // Calculate x^T * (A * x)
        return $this->dotProduct($x, $ax);
    }
}
